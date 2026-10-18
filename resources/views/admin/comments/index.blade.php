@extends('layouts.admin')

@section('title', 'Comments Moderation - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-[#87929a]">Community // Moderation</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Comments Moderation</h1>
                <p class="mt-2 text-sm text-[#bdc8d1]">Search, filter and moderate comments across all articles.</p>
            </div>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="space-y-4 rounded-xl bg-[#191c21] p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.comments.index') }}" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_180px_150px_auto_auto] lg:items-center">
                <label class="sr-only" for="comment-search">Search comments</label>
                <input id="comment-search" name="search" value="{{ $search }}" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none" placeholder="Search comment or author..." type="search">
                <label class="sr-only" for="comment-article">Filter by article</label>
                <input id="comment-article" name="article" value="{{ $articleSearch }}" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none" placeholder="Filter by article title..." type="search">
                <label class="sr-only" for="comment-status">Status</label>
                <select id="comment-status" name="status" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="comment-per-page">Comments per page</label>
                <select id="comment-per-page" name="per_page" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    @foreach ([25, 50, 100] as $pageSize)
                        <option value="{{ $pageSize }}" @selected($perPage === $pageSize)>{{ $pageSize }} / page</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-[#272a30] px-4 py-2 text-sm font-medium text-[#e1e2ea] transition hover:bg-[#32353b]">Filter</button>
                <a href="{{ route('admin.comments.index') }}" class="rounded-lg px-4 py-2 text-center text-sm text-[#bdc8d1] transition hover:bg-[#272a30]">Clear</a>
            </form>

            <div class="flex flex-wrap gap-x-5 gap-y-2 border-t border-[#32353b] pt-3 text-xs text-[#87929a]">
                <span>All: <strong class="text-[#e1e2ea]">{{ number_format((int) $statusCounts->sum()) }}</strong></span>
                @foreach ($statusOptions as $value => $label)
                    <span>{{ $label }}: <strong class="text-[#e1e2ea]">{{ number_format((int) ($statusCounts->get($value) ?? 0)) }}</strong></span>
                @endforeach
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl bg-[#191c21] shadow-sm"
            x-data="{
                selectAll: false,
                toggleAll(event) {
                    this.selectAll = event.target.checked;
                    this.$root.querySelectorAll('[data-comment-select]').forEach((input) => input.checked = this.selectAll);
                },
                confirmBulkAction(event) {
                    window.showConfirmModal({
                        title: 'Update selected comments',
                        message: 'Apply the selected status to all checked comments on this page?',
                        confirmText: 'Apply',
                    }).then((confirmed) => {
                        if (confirmed) {
                            event.target.submit();
                        }
                    });
                }
            }"
        >
            @if ($comments->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="material-symbols-outlined text-4xl text-sky-300">rate_review</span>
                    <h2 class="mt-3 font-semibold">No comments found</h2>
                    <p class="mt-1 text-sm text-[#bdc8d1]">There are no comments matching this filter.</p>
                </div>
            @else
                <form id="bulk-comments-form" method="POST" action="{{ route('admin.comments.bulk-status') }}" class="flex flex-wrap items-center gap-3 border-b border-[#32353b] bg-[#0b0e13] px-5 py-3" @submit.prevent="confirmBulkAction($event)">
                    @csrf
                    @method('PATCH')
                    <label class="font-mono text-[10px] uppercase tracking-[0.12em] text-[#87929a]" for="bulk-status">Selected comments</label>
                    <select id="bulk-status" name="status" required class="rounded-lg border border-[#32353b] bg-[#191c21] px-3 py-2 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                        <option value="">Choose action...</option>
                        <option value="VISIBLE">Approve / show</option>
                        <option value="HIDDEN">Hide</option>
                        <option value="DELETED">Delete</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">Apply</button>
                </form>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="border-b border-[#32353b] bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#87929a]">
                            <tr>
                                <th class="w-12 px-5 py-3 font-medium">
                                    <label class="sr-only" for="select-all-comments">Select all comments on this page</label>
                                    <input id="select-all-comments" class="size-4 rounded border-[#59616a] bg-[#0b0e13] text-sky-500 focus:ring-sky-400" type="checkbox" @change="toggleAll($event)">
                                </th>
                                <th class="px-5 py-3 font-medium">Comment</th>
                                <th class="px-4 py-3 font-medium">Article</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#32353b]/70">
                            @foreach ($comments as $comment)
                                <tr class="transition-colors hover:bg-[#1d2025]">
                                    <td class="px-5 py-4 align-top">
                                        <input form="bulk-comments-form" name="comment_ids[]" value="{{ $comment->id }}" data-comment-select class="size-4 rounded border-[#59616a] bg-[#0b0e13] text-sky-500 focus:ring-sky-400" type="checkbox" aria-label="Select comment by {{ $comment->user->name }}">
                                    </td>
                                    <td class="max-w-[34rem] px-5 py-4 align-top">
                                        <p class="font-semibold text-[#e1e2ea]">{{ $comment->user->name }}</p>
                                        <p class="mt-1 whitespace-pre-wrap break-words text-[#bdc8d1]">{{ $comment->content }}</p>
                                        @if ($comment->parent)
                                            <p class="mt-2 text-xs text-[#87929a]">Reply to: {{ str($comment->parent->content)->limit(90) }}</p>
                                        @endif
                                        <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.08em] text-[#87929a]">{{ $comment->created_at->format('d/m/Y H:i') }}</p>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if ($comment->post && ! $comment->post->trashed())
                                            <a class="text-sky-300 hover:text-sky-200" href="{{ route('articles.show', ['article' => $comment->post->slug]) }}" target="_blank" rel="noreferrer">{{ $comment->post->title }}</a>
                                        @elseif ($comment->post)
                                            <span class="text-[#87929a]">{{ $comment->post->title }} (deleted)</span>
                                        @else
                                            <span class="text-[#87929a]">Article unavailable</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-amber-500/10 text-amber-300' => $comment->status === 'PENDING',
                                            'bg-emerald-500/10 text-emerald-300' => $comment->status === 'VISIBLE',
                                            'bg-red-500/10 text-red-300' => in_array($comment->status, ['HIDDEN', 'DELETED'], true),
                                        ])>{{ ucfirst(strtolower($comment->status)) }}</span>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <div class="flex justify-end gap-2">
                                            @foreach (['VISIBLE' => 'Approve', 'HIDDEN' => 'Hide', 'DELETED' => 'Delete'] as $status => $label)
                                                @if ($comment->status !== $status)
                                                    <form method="POST" action="{{ route('admin.comments.update-status', $comment) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $status }}">
                                                        <button class="rounded-md border border-[#32353b] px-3 py-2 text-xs text-[#bdc8d1] transition hover:border-sky-400 hover:text-sky-300" type="submit">{{ $label }}</button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-[#32353b] px-5 py-4">
                    {{ $comments->links() }}
                </div>
            @endif
        </section>
    </div>
    <x-confirm-modal />
@endsection
