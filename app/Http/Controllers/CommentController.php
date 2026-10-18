<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Gate;
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
            ->select(['id', 'post_id', 'user_id', 'parent_id', 'content', 'status', 'created_at'])
            ->visible()
            ->roots()
            ->with(['user:id,name,avatar_url'])
            ->withCount([
                'replies as visible_replies_count' => function (Builder $query): void {
                    $query->visible();
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                $this->pageSize($request, Comment::ROOT_PAGE_SIZE, 20),
                ['id', 'post_id', 'user_id', 'parent_id', 'content', 'status', 'created_at'],
                'comments_cursor',
            );

        return CommentResource::collection($comments);
    }

    public function replies(Request $request, Post $article, Comment $comment): AnonymousResourceCollection|JsonResponse
    {
        $this->assertPublicArticle($article);
        abort_unless(
            $comment->post_id === $article->id
                && $comment->status === Comment::STATUS_VISIBLE,
            404,
        );

        if ($invalidCursor = $this->invalidCursorResponse($request, 'replies_cursor')) {
            return $invalidCursor;
        }

        $replies = $comment->replies()
            ->select(['id', 'post_id', 'user_id', 'parent_id', 'content', 'status', 'created_at'])
            ->visible()
            ->with(['user:id,name,avatar_url'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                $this->pageSize($request, Comment::REPLY_PAGE_SIZE, 20),
                ['id', 'post_id', 'user_id', 'parent_id', 'content', 'status', 'created_at'],
                'replies_cursor',
            );

        return CommentResource::collection($replies);
    }

    public function store(StoreCommentRequest $request, Post $article): JsonResponse
    {
        $this->assertPublicArticle($article);

        $comment = Comment::query()->create([
            'post_id' => $article->id,
            'user_id' => $request->user()->id,
            'parent_id' => $request->validated('parent_id'),
            'content' => $request->validated('content'),
            'status' => Comment::STATUS_VISIBLE,
        ]);

        $comment->load('user:id,name,avatar_url');
        $comment->setAttribute('visible_replies_count', 0);

        return (new CommentResource($comment))->response()->setStatusCode(201);
    }

    public function update(UpdateCommentRequest $request, Post $article, Comment $comment): CommentResource
    {
        $this->assertPublicArticle($article);

        $comment->update(['content' => $request->validated('content')]);
        $comment->load('user:id,name,avatar_url');
        $comment->setAttribute('visible_replies_count', $comment->replies()->visible()->count());

        return new CommentResource($comment);
    }

    public function destroy(Request $request, Post $article, Comment $comment): JsonResponse
    {
        $this->assertPublicArticle($article);
        Gate::forUser($request->user())->authorize('delete', $comment);

        $comment->update([
            'status' => Comment::STATUS_DELETED,
            'moderated_by' => null,
            'moderated_at' => null,
        ]);

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

        $cursor = is_string($encodedCursor) ? Cursor::fromEncoded($encodedCursor) : null;

        if ($cursor === null) {
            return response()->json(['message' => 'The pagination cursor is invalid.'], 422);
        }

        try {
            $cursor->parameters(['created_at', 'id']);
        } catch (UnexpectedValueException) {
            return response()->json(['message' => 'The pagination cursor is invalid.'], 422);
        }

        return null;
    }
}
