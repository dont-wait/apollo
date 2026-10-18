@extends('layouts.admin')

@section('title', 'Comments Moderation - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-[#87929a]">Community // Moderation</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Comments Moderation</h1>
                <p class="mt-2 text-sm text-[#bdc8d1]">Review new comments before they appear publicly.</p>
            </div>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-xl bg-[#191c21] p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.comments.index') }}" class="flex flex-wrap items-center gap-3">
                <label class="font-mono text-[10px] uppercase tracking-[0.12em] text-[#87929a]" for="comment-status">Status</label>
                <select id="comment-status" name="status" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-[#272a30] px-4 py-2 text-sm font-medium text-[#e1e2ea] transition hover:bg-[#32353b]">Filter</button>
                <a href="{{ route('admin.comments.index') }}" class="rounded-lg px-4 py-2 text-sm text-[#bdc8d1] transition hover:bg-[#272a30]">Clear</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl bg-[#191c21] shadow-sm">
            @if ($comments->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="material-symbols-outlined text-4xl text-sky-300">rate_review</span>
                    <h2 class="mt-3 font-semibold">No comments found</h2>
                    <p class="mt-1 text-sm text-[#bdc8d1]">There are no comments matching this filter.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="border-b border-[#32353b] bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#87929a]">
                            <tr>
                                <th class="px-5 py-3 font-medium">Comment</th>
                                <th class="px-4 py-3 font-medium">Article</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#32353b]/70">
                            @foreach ($comments as $comment)
                                <tr class="transition-colors hover:bg-[#1d2025]">
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
@endsection
