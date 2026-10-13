@extends('layouts.app')

@section('title', $article->title.' · Apollo Blog')

@section('content')
    <article class="mx-auto max-w-4xl">
        <a class="inline-flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-[0.12em] text-on-surface-variant transition-colors hover:text-primary" href="{{ route('home') }}#articles">
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_back</span>
            Back to articles
        </a>

        <header class="mt-8 border-b border-outline-variant/30 pb-8">
            <div class="flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-[0.1em]">
                <span class="inline-flex items-center gap-1.5 rounded bg-tertiary/10 px-2 py-0.5 text-tertiary">
                    <span class="size-1.5 rounded-full bg-tertiary"></span>
                    Published
                </span>
                @if ($article->category)
                    <span class="text-outline-variant">{{ $article->category->name }}</span>
                @endif
                <span class="text-outline-variant">·</span>
                <span class="text-outline">{{ $article->published_at?->format('M d, Y') }}</span>
            </div>

            <h1 class="mt-5 font-display text-4xl font-bold leading-tight tracking-[-0.035em] text-on-surface sm:text-5xl">
                {{ $article->title }}
            </h1>

            @if ($article->excerpt)
                <p class="mt-5 max-w-3xl text-lg leading-8 text-on-surface-variant">{{ $article->excerpt }}</p>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-3 font-mono text-[10px] uppercase tracking-[0.1em] text-on-surface-variant">
                <span class="inline-flex items-center gap-2">
                    <span class="flex size-6 items-center justify-center rounded-full bg-primary/15 text-primary">{{ str($article->author?->name ?? 'A')->substr(0, 1)->upper() }}</span>
                    {{ $article->author?->name ?? 'Apollo Editorial' }}
                </span>
                <span class="text-outline-variant">·</span>
                <span>{{ number_format($article->view_count) }} views</span>
            </div>
        </header>

        @if ($article->thumbnail)
            <img class="mt-8 max-h-[30rem] w-full rounded-xl object-cover shadow-xl shadow-black/20" src="{{ $article->thumbnail->url }}" alt="{{ $article->title }}">
        @endif

        <div class="mt-8 text-base leading-8 text-on-surface-variant [&_a]:text-primary [&_a]:underline [&_blockquote]:my-6 [&_blockquote]:border-l-2 [&_blockquote]:border-primary [&_blockquote]:pl-5 [&_code]:rounded [&_code]:bg-surface-high [&_code]:px-1.5 [&_code]:py-0.5 [&_h1]:mb-5 [&_h1]:font-display [&_h1]:text-3xl [&_h1]:font-semibold [&_h1]:text-on-surface [&_h2]:mb-4 [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-on-surface [&_h3]:mb-3 [&_h3]:mt-6 [&_h3]:font-display [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-on-surface [&_li]:my-1 [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-5 [&_pre]:my-6 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-surface-low [&_pre]:p-4 [&_pre]:text-sm [&_pre]:leading-6 [&_strong]:font-semibold [&_strong]:text-on-surface [&_ul]:list-disc">
            {!! $content !!}
        </div>

        @if ($article->tags->isNotEmpty())
            <footer class="mt-10 flex flex-wrap gap-2 border-t border-outline-variant/30 pt-5">
                @foreach ($article->tags as $tag)
                    <span class="rounded bg-surface-high px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.08em] text-on-surface-variant">#{{ $tag->name }}</span>
                @endforeach
            </footer>
        @endif
    </article>
@endsection
