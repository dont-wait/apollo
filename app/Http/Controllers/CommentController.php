<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    public function index(Request $request, Post $article): AnonymousResourceCollection
    {
        $this->assertPublicArticle($article);

        $comments = $article->comments()
            ->select(['id', 'post_id', 'user_id', 'parent_id', 'content', 'created_at'])
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
                ['id', 'post_id', 'user_id', 'parent_id', 'content', 'created_at'],
                'comments_cursor',
            );

        return CommentResource::collection($comments);
    }

    public function replies(Request $request, Post $article, Comment $comment): AnonymousResourceCollection
    {
        $this->assertPublicArticle($article);
        abort_unless(
            $comment->post_id === $article->id
                && $comment->parent_id === null
                && $comment->status === Comment::STATUS_VISIBLE,
            404,
        );

        $replies = $comment->replies()
            ->select(['id', 'post_id', 'user_id', 'parent_id', 'content', 'created_at'])
            ->visible()
            ->with(['user:id,name,avatar_url'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(
                $this->pageSize($request, Comment::REPLY_PAGE_SIZE, 20),
                ['id', 'post_id', 'user_id', 'parent_id', 'content', 'created_at'],
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
}
