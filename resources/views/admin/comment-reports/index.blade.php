@extends('layouts.admin')

@section('title', 'Comment Reports - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-background p-4 text-on-surface sm:p-8">
        <header>
            <p class="text-[11px] uppercase tracking-[0.16em] text-outline">Community // Moderation</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Comment Reports</h1>
            <p class="mt-2 text-sm text-on-surface-variant">Review complaints from readers about negative or abusive comments.</p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">{{ $errors->first() }}</div>
        @endif

        <nav class="flex flex-wrap gap-2" aria-label="Report status">
            @foreach ($statusOptions as $value => $label)
                <a href="{{ route('admin.comment-reports.index', ['status' => $value]) }}" @class([
                    'rounded-lg px-4 py-2 text-sm transition',
                    'bg-sky-500 font-semibold text-slate-950' => $selectedStatus === $value,
                    'bg-surface-high text-on-surface-variant hover:bg-surface-highest' => $selectedStatus !== $value,
                ])>{{ $label }} ({{ number_format((int) ($statusCounts->get($value) ?? 0)) }})</a>
            @endforeach
        </nav>

        <section class="overflow-hidden rounded-xl bg-surface-low shadow-sm" x-data="{
            confirmAction(event) {
                const labels = { hide: 'hide this comment', delete: 'delete this comment', dismiss: 'dismiss this report' };
                const action = event.target.elements.action.value;
                window.showConfirmModal({
                    title: 'Handle report',
                    message: `Are you sure you want to ${labels[action]}?`,
                    confirmText: 'Continue',
                }).then((confirmed) => { if (confirmed) { event.target.submit(); } });
            }
        }">
            @if ($reports->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="material-symbols-outlined text-4xl text-sky-300">flag</span>
                    <h2 class="mt-3 font-semibold">No reports found</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">There are no reports with this status.</p>
                </div>
            @else
                <div class="divide-y divide-surface-highest/70">
                    @foreach ($reports as $report)
                        @php($comment = $report->comment)
                        <article class="grid gap-4 px-5 py-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                            <div class="min-w-0 space-y-2">
                                <p class="text-xs text-outline">
                                    <span class="rounded-full bg-amber-500/10 px-2.5 py-1 font-medium text-amber-300">{{ \App\Models\CommentReport::REASONS[$report->reason] ?? $report->reason }}</span>
                                    · reported by <strong class="text-on-surface">{{ $report->reporter?->name ?? 'Deleted user' }}</strong>
                                    · {{ $report->created_at->format('d/m/Y H:i') }}
                                    @if (($openCounts[$report->comment_id] ?? 0) > 1)
                                        · <span class="text-red-300">{{ $openCounts[$report->comment_id] }} open reports on this comment</span>
                                    @endif
                                </p>
                                @if ($report->details)
                                    <p class="whitespace-pre-wrap break-words text-sm text-on-surface-variant">“{{ $report->details }}”</p>
                                @endif
                                <div class="rounded-lg border border-surface-highest bg-surface-lowest p-3">
                                    <p class="text-sm font-semibold">{{ $comment?->user?->name ?? 'Deleted user' }}
                                        <span class="ml-2 text-xs font-normal text-outline">{{ ucfirst(strtolower($comment?->status ?? '')) }}</span>
                                    </p>
                                    <p class="mt-1 whitespace-pre-wrap break-words text-sm text-on-surface-variant">{{ $comment?->content }}</p>
                                    @if ($comment?->post)
                                        <p class="mt-2 text-xs text-outline">On:
                                            @if (! $comment->post->trashed())
                                                <a class="text-sky-300 hover:text-sky-200" href="{{ route('articles.show', ['article' => $comment->post->slug]) }}" target="_blank" rel="noreferrer">{{ $comment->post->title }}</a>
                                            @else
                                                {{ $comment->post->title }} (deleted)
                                            @endif
                                        </p>
                                    @endif
                                </div>
                                @if ($report->status !== \App\Models\CommentReport::STATUS_OPEN)
                                    <p class="text-xs text-outline">{{ ucfirst(strtolower($report->status)) }} by {{ $report->handler?->name ?? 'unknown' }} · {{ $report->handled_at?->format('d/m/Y H:i') }}</p>
                                @endif
                            </div>
                            @if ($report->status === \App\Models\CommentReport::STATUS_OPEN)
                                <div class="flex flex-wrap items-start gap-2 lg:flex-col">
                                    @foreach (['hide' => 'Hide comment', 'delete' => 'Delete comment', 'dismiss' => 'Dismiss'] as $action => $label)
                                        <form method="POST" action="{{ route('admin.comment-reports.update', $report) }}" @submit.prevent="confirmAction($event)">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="action" value="{{ $action }}">
                                            <button class="w-full rounded-md border border-surface-highest px-3 py-2 text-xs text-on-surface-variant transition hover:border-sky-400 hover:text-sky-300" type="submit">{{ $label }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
                <div class="border-t border-surface-highest px-5 py-4">{{ $reports->links() }}</div>
            @endif
        </section>
    </div>
    <x-confirm-modal />
@endsection
