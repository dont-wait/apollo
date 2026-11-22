<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentReportRequest;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CommentReportController extends Controller
{
    public function store(StoreCommentReportRequest $request, Post $article, Comment $comment): JsonResponse
    {
        abort_unless($article->isPublished(), 404);

        $user = $request->user();

        if ($comment->user_id === $user->id) {
            return response()->json(['message' => 'You cannot report your own comment.'], 403);
        }

        // Lock the comment so report creation is serialized with admin moderation,
        // then re-check visibility on the locked row.
        $report = DB::transaction(function () use ($comment, $request, $user): CommentReport {
            $locked = Comment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $locked->status === Comment::STATUS_VISIBLE && $locked->isPubliclyAccessible(),
                404,
            );

            return CommentReport::query()->firstOrCreate(
                ['comment_id' => $locked->id, 'reporter_id' => $user->id],
                [
                    'reason' => $request->validated('reason'),
                    'details' => $request->validated('details'),
                    'status' => CommentReport::STATUS_OPEN,
                ],
            );
        }, attempts: 3);

        return response()->json(
            ['message' => $report->wasRecentlyCreated
                ? 'Thanks, your report has been sent to our moderators.'
                : 'You have already reported this comment.'],
            $report->wasRecentlyCreated ? 201 : 200,
        );
    }
}
