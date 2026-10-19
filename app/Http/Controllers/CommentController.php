<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class CommentController extends Controller
{
    public function index(Request $request, Post $article): AnonymousResourceCollection|JsonResponse
    {
        $this->assertPublicArticle($article);

        if ($invalidCursor = $this->invalidCursorResponse($request, 'comments_cursor')) {
            return $invalidCursor;
        }

        $comments = $article->comments()
            ->select(['id', 'post_id', 'user_id', 'parent_id', 'content', 'status', 'version', 'created_at', 'updated_at', 'edited_at'])
            ->publiclyVisible()
            ->roots()
            ->with(['user:id,name,avatar_url'])
            ->withCount([
                'replies as visible_replies_count' => function (Builder $query): void {
                    $query->publiclyVisible();
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                $this->pageSize($request, Comment::ROOT_PAGE_SIZE, 20),
                ['id', 'post_id', 'user_id', 'parent_id', 'content', 'status', 'version', 'created_at', 'updated_at', 'edited_at'],
                'comments_cursor',
            );

        return CommentResource::collection($comments);
    }

    public function replies(Request $request, Post $article, Comment $comment): AnonymousResourceCollection|JsonResponse
    {
        $this->assertPublicArticle($article);
        abort_unless(
            $comment->post_id === $article->id
                && $comment->isPubliclyAccessible(),
            404,
        );

        if ($invalidCursor = $this->invalidCursorResponse($request, 'replies_cursor')) {
            return $invalidCursor;
        }

        $replies = $comment->replies()
            ->where('post_id', $article->id)
            ->select(['id', 'post_id', 'user_id', 'parent_id', 'content', 'status', 'version', 'created_at', 'updated_at', 'edited_at'])
            ->publiclyVisible()
            ->with(['user:id,name,avatar_url'])
            ->withCount([
                'replies as visible_replies_count' => function (Builder $query): void {
                    $query->publiclyVisible();
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                $this->pageSize($request, Comment::REPLY_PAGE_SIZE, 20),
                ['id', 'post_id', 'user_id', 'parent_id', 'content', 'status', 'version', 'created_at', 'updated_at', 'edited_at'],
                'replies_cursor',
            );

        return CommentResource::collection($replies);
    }

    public function store(StoreCommentRequest $request, Post $article): JsonResponse
    {
        $this->assertPublicArticle($article);

        $userId = $request->user()->id;
        $parentId = $request->validated('parent_id');
        $content = $request->validated('content');
        $idempotencyKey = $request->validated('idempotency_key');
        $idempotencyHash = hash('sha256', implode('|', [$article->id, $parentId ?? 'root', $content]));

        try {
            return DB::transaction(function () use ($article, $content, $idempotencyHash, $idempotencyKey, $parentId, $userId): JsonResponse {
                if ($idempotencyKey !== null) {
                    $existing = Comment::query()
                        ->where('user_id', $userId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        return $this->idempotentCommentResponse($existing, $idempotencyHash);
                    }
                }

                if ($parentId !== null) {
                    $parent = $this->lockCommentThread((int) $parentId);

                    if ($parent === null
                        || $parent->post_id !== $article->id
                        || $parent->status !== Comment::STATUS_VISIBLE
                        || $parent->hasBlockedPublicAncestor()) {
                        throw ValidationException::withMessages([
                            'parent_id' => 'This conversation is no longer available.',
                        ]);
                    }
                }

                $comment = Comment::query()->create([
                    'post_id' => $article->id,
                    'user_id' => $userId,
                    'parent_id' => $parentId,
                    'content' => $content,
                    'status' => Comment::STATUS_VISIBLE,
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_hash' => $idempotencyKey === null ? null : $idempotencyHash,
                ]);

                return $this->commentCreatedResponse($comment);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($idempotencyKey === null) {
                throw $exception;
            }

            $existing = Comment::query()
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->idempotentCommentResponse($existing, $idempotencyHash);
        }
    }

    public function update(UpdateCommentRequest $request, Post $article, Comment $comment): CommentResource|JsonResponse
    {
        $this->assertPublicArticle($article);

        $updated = Comment::query()
            ->whereKey($comment->id)
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', Comment::STATUS_DELETED)
            ->where('version', $request->integer('version'))
            ->update([
                'content' => $request->validated('content'),
                'edited_at' => now(),
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return response()->json(['message' => 'This comment was changed. Please refresh and try again.'], 409);
        }

        $comment->refresh();
        $comment->load('user:id,name,avatar_url');
        $comment->setAttribute('visible_replies_count', $comment->replies()->publiclyVisible()->count());

        return new CommentResource($comment);
    }

    public function destroy(Request $request, Post $article, Comment $comment): JsonResponse
    {
        $this->assertPublicArticle($article);

        DB::transaction(function () use ($comment, $request): void {
            $lockedComment = Comment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($request->user())->authorize('delete', $lockedComment);

            Comment::query()->whereKey($lockedComment->id)->update([
                'status' => Comment::STATUS_DELETED,
                'moderated_by' => null,
                'moderated_at' => null,
                'deleted_by' => $request->user()->id,
                'deleted_at' => now(),
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
        }, attempts: 3);

        return response()->json(['message' => 'Comment deleted.']);
    }

    private function assertPublicArticle(Post $article): void
    {
        abort_unless(
            $article->status === PostStatus::Published->value
                && $article->published_at?->lessThanOrEqualTo(now())
                && ($article->category_id === null || $article->category()->where('status', 'ACTIVE')->exists()),
            404,
        );
    }

    private function pageSize(Request $request, int $default, int $maximum): int
    {
        return min(max($request->integer('per_page', $default), 1), $maximum);
    }

    private function invalidCursorResponse(Request $request, string $cursorName): ?JsonResponse
    {
        $encodedCursor = $request->query($cursorName);

        if ($encodedCursor === null) {
            return null;
        }

        try {
            $cursor = is_string($encodedCursor) ? Cursor::fromEncoded($encodedCursor) : null;

            if ($cursor === null) {
                throw new UnexpectedValueException;
            }

            $parameters = $cursor->parameters(['created_at', 'id']);
            $cursorData = $cursor->toArray();

            if (! is_string($parameters[0])
                || ! is_int($parameters[1]) && ! (is_string($parameters[1]) && ctype_digit($parameters[1]))
                || (int) $parameters[1] < 1
                || ! is_bool($cursorData['_pointsToNextItems'] ?? null)) {
                throw new UnexpectedValueException;
            }

            CarbonImmutable::parse($parameters[0]);
        } catch (\Throwable) {
            return response()->json(['message' => 'The pagination cursor is invalid.'], 422);
        }

        return null;
    }

    private function lockCommentThread(int $commentId): ?Comment
    {
        $comment = Comment::query()->whereKey($commentId)->lockForUpdate()->first();

        if ($comment === null) {
            return null;
        }

        $ancestorId = $comment->parent_id;
        $visited = [];

        while ($ancestorId !== null) {
            if (isset($visited[$ancestorId])) {
                return null;
            }

            $visited[$ancestorId] = true;
            $ancestor = Comment::query()->whereKey($ancestorId)->lockForUpdate()->first();

            if ($ancestor === null) {
                return null;
            }

            $ancestorId = $ancestor->parent_id;
        }

        return $comment;
    }

    private function commentCreatedResponse(Comment $comment): JsonResponse
    {
        $comment->load('user:id,name,avatar_url');
        $comment->setAttribute('visible_replies_count', $comment->replies()->publiclyVisible()->count());

        return (new CommentResource($comment))->response()->setStatusCode(201);
    }

    private function idempotentCommentResponse(Comment $comment, string $idempotencyHash): JsonResponse
    {
        if (! hash_equals((string) $comment->idempotency_hash, $idempotencyHash)) {
            return response()->json(['message' => 'This idempotency key was already used for different content.'], 409);
        }

        return $this->commentCreatedResponse($comment);
    }
}
