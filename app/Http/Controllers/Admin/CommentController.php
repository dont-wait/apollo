<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdateCommentStatusRequest;
use App\Http\Requests\Admin\UpdateCommentStatusRequest;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $articleSearch = trim($request->string('article')->toString());
        $perPage = min(max($request->integer('per_page', 25), 10), 100);
        $statusOptions = [
            Comment::STATUS_PENDING => 'Pending',
            Comment::STATUS_VISIBLE => 'Visible',
            Comment::STATUS_HIDDEN => 'Hidden',
            Comment::STATUS_DELETED => 'Deleted',
        ];

        $comments = Comment::query()
            ->with([
                'user' => function (BelongsTo $relation): void {
                    $relation->withTrashed()->select('id', 'name');
                },
                'post' => function (BelongsTo $relation): void {
                    $relation->withTrashed()->select('id', 'title', 'slug', 'deleted_at');
                },
                'parent:id,content',
            ])
            ->when(
                array_key_exists($status, $statusOptions),
                fn (Builder $query): Builder => $query->where('status', $status),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereFullText('content', $search)
                        ->orWhereHas('user', fn (Builder $userQuery): Builder => $userQuery->whereFullText('name', $search));
                });
            })
            ->when($articleSearch !== '', function (Builder $query) use ($articleSearch): void {
                $query->whereHas('post', function (Builder $postQuery) use ($articleSearch): void {
                    $postQuery->withTrashed()->where(function (Builder $query) use ($articleSearch): void {
                        $query->whereFullText('title', $articleSearch)
                            ->orWhere('title', $articleSearch);
                    });
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $statusCounts = Comment::query()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.comments.index', [
            'comments' => $comments,
            'statusOptions' => $statusOptions,
            'selectedStatus' => $status,
            'search' => $search,
            'articleSearch' => $articleSearch,
            'perPage' => $perPage,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function bulkUpdateStatus(BulkUpdateCommentStatusRequest $request): RedirectResponse
    {
        $status = $request->validated('status');
        $updated = 0;
        $commentVersions = $request->validated('comment_versions');

        DB::transaction(function () use ($commentVersions, $request, $status, &$updated): void {
            foreach ($request->validated('comment_ids') as $commentId) {
                $version = $commentVersions[$commentId] ?? null;

                if ($version === null) {
                    continue;
                }

                $query = Comment::query()
                    ->whereKey($commentId)
                    ->where('version', $version);

                if ($status !== Comment::STATUS_DELETED) {
                    $query->where('status', '!=', Comment::STATUS_DELETED);
                }

                $updated += $query->update([
                    'status' => $status,
                    'moderated_by' => $request->user()->id,
                    'moderated_at' => now(),
                    'deleted_by' => $status === Comment::STATUS_DELETED ? $request->user()->id : null,
                    'deleted_at' => $status === Comment::STATUS_DELETED ? now() : null,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);
            }
        }, attempts: 3);

        return back()->with('status', $updated.' comment(s) updated.');
    }

    public function updateStatus(UpdateCommentStatusRequest $request, Comment $comment): RedirectResponse
    {
        $status = $request->validated('status');
        $restore = $request->boolean('restore');
        $updated = false;

        DB::transaction(function () use ($comment, $request, $restore, $status, &$updated): void {
            $lockedComment = Comment::query()->whereKey($comment->id)->lockForUpdate()->firstOrFail();

            if ($lockedComment->version !== $request->integer('version')) {
                return;
            }

            if ($lockedComment->status === Comment::STATUS_DELETED && ! $restore) {
                return;
            }

            if ($restore && $lockedComment->status !== Comment::STATUS_DELETED) {
                return;
            }

            if ($restore && $status !== Comment::STATUS_VISIBLE) {
                return;
            }

            Comment::query()->whereKey($lockedComment->id)->update([
                'status' => $status,
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
                'deleted_by' => $status === Comment::STATUS_DELETED ? $request->user()->id : null,
                'deleted_at' => $status === Comment::STATUS_DELETED ? now() : null,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
            $updated = true;
        }, attempts: 3);

        if (! $updated) {
            return back()->withErrors(['status' => 'This comment changed or was deleted. Refresh before moderating it.']);
        }

        return back()->with('status', 'Comment status updated.');
    }
}
