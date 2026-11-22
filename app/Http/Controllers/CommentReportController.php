<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\StoreCommentReportRequest;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class CommentReportController extends Controller
{
    public function store(StoreCommentReportRequest $request, Post $article, Comment $comment): JsonResponse
    {
        abort_unless(
            $article->status === PostStatus::Published->value
                && $article->published_at?->lessThanOrEqualTo(now())
                && $comment->status === Comment::STATUS_VISIBLE
                && $comment->isPubliclyAccessible(),
            404,
        );

        $user = $request->user();

        if ($comment->user_id === $user->id) {
            return response()->json(['message' => 'You cannot report your own comment.'], 403);
        }

        $report = CommentReport::query()->firstOrCreate(
            ['comment_id' => $comment->id, 'reporter_id' => $user->id],
            [
                'reason' => $request->validated('reason'),
                'details' => $request->validated('details'),
                'status' => CommentReport::STATUS_OPEN,
            ],
        );

        return response()->json(
            ['message' => $report->wasRecentlyCreated
                ? 'Thanks, your report has been sent to our moderators.'
                : 'You have already reported this comment.'],
            $report->wasRecentlyCreated ? 201 : 200,
        );
    }
}
