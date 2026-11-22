@extends('layouts.admin')

@section('title', 'Articles - Apollo Admin')

@section('content')
    @php
        $statusMeta = [
            'PUBLISHED' => ['label' => 'Published', 'badge' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300', 'dot' => 'bg-emerald-400', 'bar' => 'bg-emerald-400', 'icon' => 'check_circle'],
            'SCHEDULED' => ['label' => 'Scheduled', 'badge' => 'border-sky-500/30 bg-sky-500/10 text-sky-300', 'dot' => 'bg-sky-400', 'bar' => 'bg-sky-400', 'icon' => 'schedule'],
            'DRAFT' => ['label' => 'Draft', 'badge' => 'border-surface-highest bg-surface-high text-on-surface-variant', 'dot' => 'bg-outline', 'bar' => 'bg-outline', 'icon' => 'edit_note'],
            'REVIEW' => ['label' => 'Review', 'badge' => 'border-amber-500/30 bg-amber-500/10 text-amber-300', 'dot' => 'bg-amber-400', 'bar' => 'bg-amber-400', 'icon' => 'rate_review'],
            'ARCHIVED' => ['label' => 'Archived', 'badge' => 'border-surface-highest bg-surface-high text-outline', 'dot' => 'bg-outline-variant', 'bar' => 'bg-outline-variant', 'icon' => 'inventory_2'],
        ];
        $count = fn (string $status): int => (int) ($statusCounts[$status] ?? 0);
        $share = fn (int $value): int => $totalArticles > 0 ? (int) round($value / $totalArticles * 100) : 0;
        $keep = request()->only(['q', 'category_id', 'author_id', 'sort']);
        $statusUrl = fn (?string $status): string => route('admin.articles.index', array_filter([...$keep, 'status' => $status]));
        $activeStatus = $filters['status'] ?? null;
        $hasFilters = collect(['q', 'category_id', 'author_id', 'status'])->contains(fn ($key) => filled($filters[$key] ?? null)) || $sort !== ($trashed ? 'deleted' : 'updated');
        $selectClass = 'rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2 text-sm text-on-surface focus:border-sky-400 focus:outline-none';
    @endphp

    <div class="-m-8 min-h-full space-y-6 bg-background p-4 text-on-surface sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-outline">Content // Workspace</p>
                <h1 class="mt-2 flex flex-wrap items-baseline gap-3 text-3xl font-semibold tracking-tight">
                    {{ $trashed ? 'Trash' : 'Articles' }}
                    <span class="font-mono text-sm font-normal text-outline">{{ $trashed ? 'Deleted articles' : 'Article management' }}</span>
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-on-surface-variant">{{ $trashed ? 'Restore an article to bring it back with its previous status, category and tags.' : 'Manage, filter and review every published, scheduled, draft and archived article in one place.' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($trashed)
                    <a href="{{ route('admin.articles.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-surface-high px-4 py-2 text-sm font-medium text-on-surface transition hover:bg-surface-highest">
                        <span class="material-symbols-outlined text-lg">arrow_back</span>
                        Back to articles
                    </a>
                @else
                    <a href="{{ route('admin.articles.index', ['trashed' => 1]) }}" class="inline-flex items-center gap-2 rounded-lg border border-surface-highest px-3.5 py-2 text-sm font-medium text-on-surface-variant transition hover:bg-surface-high hover:text-on-surface" title="Deleted articles that can be restored">
                        <span class="material-symbols-outlined text-lg">delete_sweep</span>
                        Trash
                        @if ($trashedCount > 0)
                            <span class="rounded-full bg-red-500/15 px-1.5 font-mono text-[10px] tabular-nums text-red-300">{{ $trashedCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.articles.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">
                        <span class="material-symbols-outlined text-lg">add_circle</span>
                        Write new article
                    </a>
                @endif
            </div>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Status overview --}}
        @unless ($trashed)
        <section aria-label="Article status overview" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            <a href="{{ $statusUrl(null) }}" @class(['group rounded-xl border bg-surface-low p-4 transition hover:bg-surface', 'border-sky-500/50' => $activeStatus === null, 'border-surface-highest' => $activeStatus !== null])>
                <div class="flex items-center justify-between text-[11px] uppercase tracking-wider text-on-surface-variant">
                    <span>All articles</span>
                    <span class="material-symbols-outlined text-lg text-outline">library_books</span>
                </div>
                <p class="mt-3 font-mono text-4xl font-semibold tabular-nums">{{ number_format($totalArticles) }}</p>
                <p class="mt-1 text-xs text-outline">in total</p>
                <div class="mt-3 h-1 overflow-hidden rounded-full bg-surface-highest"><div class="h-full w-full rounded-full bg-sky-400"></div></div>
            </a>

            @foreach (['PUBLISHED' => 'live', 'SCHEDULED' => 'queued', 'DRAFT' => 'in progress', 'ARCHIVED' => 'hidden'] as $status => $caption)
                @php $meta = $statusMeta[$status]; $value = $count($status); @endphp
                <a href="{{ $statusUrl($status) }}" @class(['group rounded-xl border bg-surface-low p-4 transition hover:bg-surface', 'border-sky-500/50' => $activeStatus === $status, 'border-surface-highest' => $activeStatus !== $status, 'opacity-80' => $status === 'ARCHIVED'])>
                    <div class="flex items-center justify-between text-[11px] uppercase tracking-wider text-on-surface-variant">
                        <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}</span>
                        <span class="inline-flex items-center gap-1.5">
                            @if ($status === 'PUBLISHED')
                                <span class="font-mono text-emerald-300">{{ $share($value) }}%</span>
                            @else
                                <span class="material-symbols-outlined text-lg text-outline">{{ $meta['icon'] }}</span>
                            @endif
                        </span>
                    </div>
                    <p class="mt-3 font-mono text-4xl font-semibold tabular-nums">{{ number_format($value) }}</p>
                    <p class="mt-1 text-xs text-outline">{{ $caption }}</p>
                    <div class="mt-3 h-1 overflow-hidden rounded-full bg-surface-highest"><div class="h-full rounded-full {{ $meta['bar'] }}" style="width: {{ $share($value) }}%"></div></div>
                </a>
            @endforeach
        </section>
        @endunless

        {{-- Filters --}}
        <section class="space-y-3 rounded-xl bg-surface-low p-4 shadow-sm">
            @unless ($trashed)
            <nav aria-label="Filter by status" class="flex flex-wrap items-center gap-1 rounded-lg border border-surface-highest bg-surface-lowest p-1 text-xs font-medium sm:w-fit">
                @php
                    $tabs = ['' => ['All', $totalArticles]] + collect($statusFilterOptions)
                        ->filter(fn ($label, $value) => $value !== 'REVIEW' || $count('REVIEW') > 0 || $activeStatus === 'REVIEW')
                        ->map(fn ($label, $value) => [$label, $count($value)])->all();
                @endphp
                @foreach ($tabs as $value => [$label, $total])
                    <a href="{{ $statusUrl($value ?: null) }}" aria-current="{{ ($activeStatus ?? '') === (string) $value ? 'page' : 'false' }}" class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-outline transition hover:text-on-surface aria-[current=page]:bg-surface-high aria-[current=page]:text-on-surface">
                        {{ $label }}
                        <span class="rounded bg-surface-highest px-1.5 font-mono text-[10px] tabular-nums text-on-surface-variant">{{ $total }}</span>
                    </a>
                @endforeach
            </nav>
            @endunless

            <form method="GET" action="{{ route('admin.articles.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,1fr)_repeat(3,minmax(9rem,0.45fr))_auto]">
                @if ($activeStatus)
                    <input type="hidden" name="status" value="{{ $activeStatus }}">
                @endif
                @if ($trashed)
                    <input type="hidden" name="trashed" value="1">
                @endif
                <label class="sr-only" for="article-search">Search articles</label>
                <div class="relative sm:col-span-2 lg:col-span-1">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-outline">search</span>
                    <input id="article-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Search by title, slug, keyword or author..." class="w-full min-w-0 rounded-lg border border-surface-highest bg-surface-lowest py-2 pl-10 pr-3 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none">
                </div>
                <label class="sr-only" for="category-filter">Filter by category</label>
                <select id="category-filter" name="category_id" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="author-filter">Filter by author</label>
                <select id="author-filter" name="author_id" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                    <option value="">All authors</option>
                    @foreach ($authors as $author)
                        <option value="{{ $author->id }}" @selected((string) ($filters['author_id'] ?? '') === (string) $author->id)>{{ $author->name }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="sort-order">Sort articles</label>
                <select id="sort-order" name="sort" onchange="this.form.requestSubmit()" class="{{ $selectClass }}">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>Sort: {{ $label }}</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2">
                    <button type="submit" class="rounded-lg bg-surface-high px-4 py-2 text-sm font-medium text-on-surface transition hover:bg-surface-highest">Filter</button>
                    @if ($hasFilters)
                        <a href="{{ route('admin.articles.index', $trashed ? ['trashed' => 1] : []) }}" class="whitespace-nowrap rounded-lg px-3 py-2 text-center text-sm text-on-surface-variant transition hover:bg-surface-high">Clear filters</a>
                    @endif
                </div>
            </form>
        </section>

        {{-- Table --}}
        <section class="overflow-hidden rounded-xl bg-surface-low shadow-sm">
            @if ($articles->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="material-symbols-outlined text-4xl text-sky-300">{{ $trashed ? 'delete_sweep' : 'article' }}</span>
                    <h2 class="mt-3 font-semibold">{{ $trashed ? 'Trash is empty' : 'No matching articles' }}</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ $trashed ? 'Deleted articles will show up here so you can restore them.' : 'Create a new article or change the filters.' }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead class="border-b border-surface-highest bg-surface-lowest text-[10px] uppercase tracking-wider text-outline">
                            <tr>
                                <th class="px-5 py-3 font-medium">Article &amp; details</th>
                                <th class="px-4 py-3 font-medium">Status &amp; schedule</th>
                                <th class="px-4 py-3 font-medium">Category &amp; tags</th>
                                <th class="px-4 py-3 font-medium">Engagement</th>
                                <th class="px-5 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-highest/70">
                            @foreach ($articles as $article)
                                @php
                                    $meta = $statusMeta[$article->status] ?? $statusMeta['DRAFT'];
                                    $tagList = $article->tags->take(3);
                                    $moreTags = $article->tags->count() - $tagList->count();
                                @endphp
                                <tr @class(['align-top transition-colors hover:bg-surface', 'opacity-70' => $article->status === 'ARCHIVED'])>
                                    <td class="max-w-md px-5 py-4">
                                        @if ($trashed)
                                            <p class="line-clamp-2 font-semibold text-on-surface">{{ $article->title }}</p>
                                        @else
                                            <a href="{{ route('admin.articles.edit', $article) }}" class="line-clamp-2 font-semibold text-on-surface hover:text-sky-300">{{ $article->title }}</a>
                                        @endif
                                        <p class="mt-1 truncate font-mono text-xs text-sky-300/90">/articles/{{ $article->slug }}</p>
                                        <p class="mt-1.5 flex flex-wrap items-center gap-x-2 text-xs text-outline">
                                            <span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-sm">person</span>{{ $article->author?->name ?? 'Unknown author' }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span>{{ $article->readingMinutes() }} min read</span>
                                        </p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded border px-2 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wider {{ $meta['badge'] }}">
                                            <span class="size-1.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}
                                        </span>
                                        @if ($trashed)
                                            <p class="mt-1.5 text-xs text-red-300">Deleted {{ $article->deleted_at->diffForHumans() }}</p>
                                            <p class="text-[11px] text-outline">{{ $article->deleted_at->format('M j, Y · H:i') }}</p>
                                        @elseif ($article->status === 'PUBLISHED' && $article->published_at)
                                            <p class="mt-1.5 text-xs text-on-surface-variant">{{ $article->published_at->format('M j, Y · H:i') }}</p>
                                            <p class="text-[11px] text-outline">{{ config('app.timezone') }}</p>
                                        @elseif ($article->status === 'SCHEDULED' && $article->scheduled_at)
                                            <p class="mt-1.5 text-xs font-medium text-sky-300">{{ $article->scheduled_at->format('M j, Y · H:i') }}</p>
                                            <p class="text-[11px] text-outline">{{ $article->scheduled_at->isFuture() ? 'Releases in '.$article->scheduled_at->diffForHumans(null, true) : 'Overdue by '.$article->scheduled_at->diffForHumans(null, true) }}</p>
                                        @elseif ($article->status === 'ARCHIVED')
                                            <p class="mt-1.5 text-xs text-outline">Archived {{ $article->updated_at->format('M j, Y') }}</p>
                                        @else
                                            <p class="mt-1.5 text-xs text-on-surface-variant">Edited {{ $article->updated_at->diffForHumans() }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-on-surface-variant">{{ $article->category?->name ?? 'Uncategorized' }}</p>
                                        @if ($tagList->isNotEmpty())
                                            <div class="mt-1.5 flex flex-wrap gap-1">
                                                @foreach ($tagList as $tag)
                                                    <span class="rounded bg-surface-high px-1.5 py-0.5 font-mono text-[10px] text-on-surface-variant">#{{ $tag->name }}</span>
                                                @endforeach
                                                @if ($moreTags > 0)
                                                    <span class="rounded px-1.5 py-0.5 font-mono text-[10px] text-outline">+{{ $moreTags }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs tabular-nums text-on-surface-variant">
                                            <span class="inline-flex items-center gap-1" title="Views"><span class="material-symbols-outlined text-base text-outline">visibility</span>{{ number_format($article->view_count) }}</span>
                                            <span class="inline-flex items-center gap-1" title="Bookmarks"><span class="material-symbols-outlined text-base text-outline">bookmark</span>{{ number_format($article->bookmarks_count) }}</span>
                                            <span class="inline-flex items-center gap-1" title="Comments"><span class="material-symbols-outlined text-base text-outline">chat_bubble</span>{{ number_format($article->comments_count) }}</span>
                                        </div>
                                        <div class="mt-2 h-1 w-40 overflow-hidden rounded-full bg-surface-highest" title="Views relative to the most viewed article on this page">
                                            <div class="h-full rounded-full {{ $article->status === 'PUBLISHED' ? 'bg-emerald-400' : 'bg-sky-400' }}" style="width: {{ round($article->view_count / $maxViews * 100) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-1">
                                            @if ($trashed)
                                                <form method="POST" action="{{ route('admin.articles.restore', $article) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-xs font-medium text-emerald-300 transition hover:bg-emerald-500/20" aria-label="Restore {{ $article->title }}" title="Restore this article">
                                                        <span class="material-symbols-outlined text-base">restore_from_trash</span>
                                                        Restore
                                                    </button>
                                                </form>
                                            @else
                                                @if ($article->status === 'PUBLISHED')
                                                    <a href="{{ route('articles.show', $article->slug) }}" target="_blank" rel="noopener" class="rounded-md p-2 text-on-surface-variant transition hover:bg-surface-high hover:text-sky-300" aria-label="View {{ $article->title }} on the site" title="View on site">
                                                        <span class="material-symbols-outlined text-lg">open_in_new</span>
                                                    </a>
                                                @endif
                                                <a href="{{ route('admin.articles.edit', $article) }}" class="rounded-md p-2 text-on-surface-variant transition hover:bg-surface-high hover:text-sky-300" aria-label="Edit {{ $article->title }}" title="Edit">
                                                    <span class="material-symbols-outlined text-lg">edit</span>
                                                </a>
                                                <form method="POST" action="{{ route('admin.articles.destroy', $article) }}" onsubmit="return confirm('Are you sure you want to delete this article?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-md p-2 text-on-surface-variant transition hover:bg-red-500/10 hover:text-red-300" aria-label="Delete {{ $article->title }}" title="Delete">
                                                        <span class="material-symbols-outlined text-lg">delete</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t border-surface-highest px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-on-surface-variant">
                        Showing <span class="font-semibold text-on-surface">{{ $articles->firstItem() }}–{{ $articles->lastItem() }}</span>
                        of {{ number_format($articles->total()) }} {{ \Illuminate\Support\Str::plural('article', $articles->total()) }}
                    </p>
                    <div>{{ $articles->links() }}</div>
                </div>
            @endif
        </section>
    </div>
@endsection
