@extends('layouts.admin')

@section('title', 'AI Articles - Apollo Admin')

@section('content')
<div class="-m-8 min-h-full space-y-6 bg-background p-4 text-on-surface sm:p-8">
    @include('admin.ai-weekly.nav')
    <div>
        <h1 class="text-3xl font-semibold">Collected articles</h1>
        <p class="mt-2 max-w-2xl text-sm text-on-surface-variant">Tick the articles you want{{ $maxSelection ? " (up to {$maxSelection})" : '' }} and analyze just those. Articles you do not pick are left alone.</p>
    </div>

    <form data-ai-select-form data-max="{{ $maxSelection ?? 0 }}" data-runs-url="{{ route('admin.automation-runs.index') }}" method="post" action="{{ route('admin.automation-runs.analyze') }}" class="space-y-3">
        @csrf
        <div class="sticky top-16 z-20 flex flex-wrap items-center gap-3 rounded-lg border border-surface-highest bg-surface-low/95 px-4 py-3 backdrop-blur">
            <button type="submit" data-selection-submit disabled class="inline-flex items-center gap-2 rounded border border-primary/30 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/20 disabled:cursor-not-allowed disabled:border-outline-variant disabled:bg-surface-container disabled:text-outline">
                <span class="material-symbols-outlined text-base" aria-hidden="true">auto_awesome</span>
                Analyze selected
            </button>
            <span data-selection-count class="font-mono text-xs text-on-surface-variant">0{{ $maxSelection ? " / {$maxSelection}" : '' }} selected</span>
            <button type="button" data-selection-clear hidden class="text-xs text-outline hover:text-on-surface">Clear</button>
            <p data-ai-feedback role="status" class="text-xs text-primary"></p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-surface-highest">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-low text-on-surface-variant">
                    <tr>
                        <th class="w-10 p-3"><span class="sr-only">Select</span></th>
                        <th class="p-3">Article</th>
                        <th class="p-3">Source</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($articles as $article)
                        @php
                            $analyzable = ! $article->duplicate_of && (filled($article->raw_summary) || filled($article->raw_content));
                            $reason = $article->duplicate_of ? 'Duplicate article' : 'No content to analyze';
                        @endphp
                        <tr class="border-t border-surface-highest hover:bg-surface">
                            <td class="p-3">
                                <input type="checkbox" name="article_ids[]" value="{{ $article->id }}" data-unavailable="{{ $analyzable ? 'false' : 'true' }}" @disabled(! $analyzable) @if (! $analyzable) title="{{ $reason }}" @endif aria-label="Select {{ $article->title }}" class="size-4 rounded border-outline-variant bg-surface-lowest text-sky-400 focus:ring-sky-400 disabled:opacity-40">
                            </td>
                            <td class="p-3"><a class="text-primary hover:underline" href="{{ route('admin.ai-articles.show', $article) }}">{{ $article->title }}</a></td>
                            <td class="p-3">{{ $article->source->name }}</td>
                            <td class="p-3">{{ $article->duplicate_of ? 'DUPLICATE' : $article->status }}</td>
                            <td class="p-3">{{ $article->published_at?->format('d/m/Y') ?? $article->collected_at?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </form>
    {{ $articles->links() }}
</div>
@endsection
