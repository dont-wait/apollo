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
                'user:id,name',
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
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('content', 'like', $like)
                        ->orWhereHas('user', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', $like));
                });
            })
            ->when($articleSearch !== '', function (Builder $query) use ($articleSearch): void {
                $like = '%'.$articleSearch.'%';

                $query->whereHas('post', function (Builder $postQuery) use ($like): void {
                    $postQuery->withTrashed()->where('title', 'like', $like);
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
        $updated = Comment::query()
            ->whereKey($request->validated('comment_ids'))
            ->update([
                'status' => $status,
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
            ]);

        return back()->with('status', $updated.' comment(s) updated.');
    }

    public function updateStatus(UpdateCommentStatusRequest $request, Comment $comment): RedirectResponse
    {
        $comment->update([
            'status' => $request->validated('status'),
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ]);

        return back()->with('status', 'Comment status updated.');
    }
}
