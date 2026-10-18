<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $statusOptions = [
            Comment::STATUS_PENDING => 'Pending',
            Comment::STATUS_VISIBLE => 'Visible',
            'HIDDEN' => 'Hidden',
            'DELETED' => 'Deleted',
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
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.comments.index', [
            'comments' => $comments,
            'statusOptions' => $statusOptions,
            'selectedStatus' => $status,
        ]);
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
