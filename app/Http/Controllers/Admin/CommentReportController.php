<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCommentReportRequest;
use App\Models\Comment;
use App\Models\CommentReport;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommentReportController extends Controller
{
    public function index(Request $request): View
    {
        $statusOptions = [
            CommentReport::STATUS_OPEN => 'Open',
            CommentReport::STATUS_RESOLVED => 'Resolved',
            CommentReport::STATUS_DISMISSED => 'Dismissed',
        ];
        $status = $request->string('status')->toString();
        $status = array_key_exists($status, $statusOptions) ? $status : CommentReport::STATUS_OPEN;

        $reports = CommentReport::query()
            ->with([
                'reporter' => fn (BelongsTo $relation) => $relation->withTrashed()->select('id', 'name'),
                'handler' => fn (BelongsTo $relation) => $relation->withTrashed()->select('id', 'name'),
                'comment.user' => fn (BelongsTo $relation) => $relation->withTrashed()->select('id', 'name'),
                'comment.post' => fn (BelongsTo $relation) => $relation->withTrashed()->select('id', 'title', 'slug', 'deleted_at'),
            ])
            ->where('status', $status)
            ->latest('created_at')
            ->latest('id')
            ->cursorPaginate(25)
            ->withQueryString();

        $commentIds = $reports->pluck('comment_id')->unique();
        $openCounts = CommentReport::query()
            ->whereIn('comment_id', $commentIds)
            ->where('status', CommentReport::STATUS_OPEN)
            ->select('comment_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('comment_id')
            ->pluck('total', 'comment_id');

        return view('admin.comment-reports.index', [
            'reports' => $reports,
            'statusOptions' => $statusOptions,
            'selectedStatus' => $status,
            'statusCounts' => CommentReport::query()
                ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'openCounts' => $openCounts,
        ]);
    }

    public function update(UpdateCommentReportRequest $request, CommentReport $report): RedirectResponse
    {
        $action = $request->validated('action');
        $moderatorId = $request->user()->id;
        $applied = false;

        DB::transaction(function () use ($action, $moderatorId, $report, &$applied): void {
            // Lock order: comment first, then report (same as report creation) to avoid deadlocks
            // and to serialize concurrent moderation of the same comment.
            $comment = Comment::query()->whereKey($report->comment_id)->lockForUpdate()->first();
            $locked = CommentReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CommentReport::STATUS_OPEN) {
                return;
            }

            $applied = true;

            if ($action === 'dismiss') {
                $locked->update([
                    'status' => CommentReport::STATUS_DISMISSED,
                    'handled_by' => $moderatorId,
                    'handled_at' => now(),
                ]);

                return;
            }

            // A comment already deleted (e.g. by its author) stays deleted so the normal
            // restore flow keeps working; only the reports are closed.
            if ($comment !== null && $comment->status !== Comment::STATUS_DELETED) {
                $newStatus = $action === 'delete' ? Comment::STATUS_DELETED : Comment::STATUS_HIDDEN;

                Comment::query()->whereKey($comment->id)->update([
                    'status' => $newStatus,
                    'moderated_by' => $moderatorId,
                    'moderated_at' => now(),
                    'deleted_by' => $newStatus === Comment::STATUS_DELETED ? $moderatorId : null,
                    'deleted_at' => $newStatus === Comment::STATUS_DELETED ? now() : null,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);
            }

            CommentReport::resolveOpenFor($locked->comment_id, $moderatorId);
        }, attempts: 3);

        if (! $applied) {
            return back()->withErrors(['report' => 'This report was already handled by another moderator.']);
        }

        return back()->with('status', 'Report updated.');
    }
}
