@extends('layouts.app')

@section('title', 'Saved Articles · Apollo Blog')

@section('content')
    <div
        x-data="savedBookmarks({{ $posts->count() }}, {{ $posts->total() }})"
        @bookmark-toggled.window="handleBookmark($event)"
        @bookmark-link-copied.window="handleLinkCopied()"
    >
        <a class="inline-flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-[0.12em] text-on-surface-variant transition-colors hover:text-primary" href="{{ route('home') }}#articles">
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_back</span>
            Back to articles
        </a>

        <header class="mt-8 flex flex-col justify-between gap-4 border-b border-outline-variant/30 pb-8 sm:flex-row sm:items-end">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-[0.14em] text-primary">Your library</p>
                <h1 class="mt-2 font-display text-3xl font-semibold tracking-tight text-on-surface">Saved Articles</h1>
                <p class="mt-2 text-sm leading-6 text-on-surface-variant">Keep the articles you want to revisit close at hand.</p>
            </div>
            <span class="font-mono text-[10px] uppercase tracking-[0.12em] text-on-surface-variant">
                <span x-text="`${savedCount} ${savedCount === 1 ? 'article' : 'articles'} saved`">{{ $posts->total() }} {{ $posts->total() === 1 ? 'article' : 'articles' }} saved</span>
            </span>
        </header>

        <div class="mt-8" aria-live="polite">
            <div x-cloak x-show="savedCount === 0" class="rounded-xl border border-dashed border-outline-variant/50 bg-surface-low p-10 text-center">
                <span class="material-symbols-outlined text-4xl text-outline" aria-hidden="true">bookmark_border</span>
                <h2 class="mt-3 font-display text-xl font-semibold text-on-surface">No saved articles yet</h2>
                <p class="mt-2 text-sm leading-6 text-on-surface-variant">Save an article from the home page or an article detail page to find it here.</p>
                <a class="mt-5 inline-flex rounded-md bg-primary px-4 py-2.5 font-display text-sm font-semibold text-on-primary transition-colors hover:bg-surface-tint" href="{{ route('home') }}#articles">Browse articles</a>
            </div>

            <div class="space-y-4" x-cloak x-show="visibleCount > 0">
                @foreach ($posts as $post)
                    <article
                        class="group relative flex flex-col gap-4 rounded-xl border border-outline-variant/30 bg-surface-low p-4 shadow-md transition-colors hover:border-primary/30 sm:flex-row"
                        x-data="savedBookmarkCard(@js([
                            'postId' => $post->id,
                            'bookmarked' => true,
                            'count' => $post->bookmarks_count,
                            'url' => route('articles.show', ['article' => $post->slug]),
                            'storeUrl' => $bookmarkUrls['store'],
                            'destroyUrl' => $bookmarkUrls['destroy'],
                        ]))"
                        x-show="!removed"
                        @bookmark-toggled.window="handleBookmark($event)"
                    >
                        <div class="absolute right-4 top-4 z-20" @click.outside="menuOpen = false">
                            <button
                                class="rounded p-1 text-on-surface-variant transition-colors hover:bg-surface-high hover:text-on-surface"
                                type="button"
                                aria-label="More options"
                                :aria-expanded="menuOpen"
                                @click="menuOpen = !menuOpen"
                            >
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">more_vert</span>
                            </button>

                            <div
                                x-cloak
                                x-show="menuOpen"
                                x-transition
                                class="absolute right-0 top-9 w-48 overflow-hidden rounded-lg border border-outline-variant/40 bg-surface-high p-1 shadow-xl"
                                role="menu"
                            >
                                <button
                                    class="flex w-full items-center gap-2 rounded px-3 py-2 text-left text-xs text-on-surface-variant transition-colors hover:bg-surface hover:text-on-surface"
                                    type="button"
                                    role="menuitem"
                                    @click="menuOpen = false; toggle()"
                                >
                                    <span class="material-symbols-outlined text-[17px]" aria-hidden="true">bookmark_remove</span>
                                    Unsave
                                </button>
                                <button
                                    class="flex w-full items-center gap-2 rounded px-3 py-2 text-left text-xs text-on-surface-variant transition-colors hover:bg-surface hover:text-on-surface"
                                    type="button"
                                    role="menuitem"
                                    @click="copyLink()"
                                >
                                    <span class="material-symbols-outlined text-[17px]" aria-hidden="true">content_copy</span>
                                    Copy link
                                </button>
                            </div>
                        </div>

                        <a class="block h-40 shrink-0 overflow-hidden rounded-lg bg-surface-lowest sm:w-52" href="{{ route('articles.show', ['article' => $post->slug]) }}">
                            @if ($post->thumbnail)
                                <img class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" src="{{ $post->thumbnail->url }}" alt="{{ $post->title }}" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-primary/5 text-primary/60">
                                    <span class="material-symbols-outlined text-4xl" aria-hidden="true">article</span>
                                </div>
                            @endif
                        </a>

                        <div class="flex min-w-0 flex-1 flex-col justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-[0.1em]">
                                    <span class="text-primary">{{ $post->category?->name ?? 'Uncategorized' }}</span>
                                    <span class="text-outline-variant">·</span>
                                    <span class="text-outline">{{ $post->published_at?->diffForHumans() }}</span>
                                </div>
                                <h2 class="mt-2 font-display text-xl font-semibold leading-tight tracking-tight text-on-surface transition-colors group-hover:text-primary">
                                    <a href="{{ route('articles.show', ['article' => $post->slug]) }}">{{ $post->title }}</a>
                                </h2>
                                @if ($post->excerpt)
                                    <p class="mt-2 line-clamp-2 text-sm leading-6 text-on-surface-variant">{{ $post->excerpt }}</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/25 pt-3">
                                <div class="flex min-w-0 items-center gap-2 font-mono text-[10px] text-on-surface-variant">
                                    <span class="size-5 rounded-full bg-primary/15 text-center leading-5 text-primary">{{ str($post->author?->name ?? 'A')->substr(0, 1)->upper() }}</span>
                                    <span class="truncate">{{ $post->author?->name ?? 'Apollo Editorial' }}</span>
                                    <span class="text-outline">Saved {{ $post->pivot->created_at?->diffForHumans() }}</span>
                                </div>
                                <span class="inline-flex items-center gap-1 font-mono text-[10px] text-primary" aria-label="Saved">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">bookmark</span>
                                    <span>Saved</span>
                                    <span x-text="count"></span>
                                </span>
                            </div>
                            <p x-cloak x-show="error" class="text-xs text-error" role="alert">Unable to update bookmark. Please try again.</p>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($posts->hasPages())
                <nav class="mt-8" aria-label="Saved articles pagination">
                    {{ $posts->links() }}
                </nav>
            @endif
        </div>

        <div x-cloak x-show="notice" x-transition class="fixed inset-x-4 bottom-4 z-50 mx-auto flex max-w-md items-center justify-between gap-4 rounded-lg border border-primary/30 bg-surface-high px-4 py-3 shadow-2xl shadow-black/30" role="status" aria-live="polite">
            <p class="text-sm text-on-surface" x-text="noticeMessage"></p>
            <button class="rounded p-1 text-outline transition-colors hover:text-on-surface" type="button" aria-label="Dismiss" @click="notice = false">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
            </button>
        </div>
    </div>
@endsection
