@extends('layouts.admin')

@section('title', 'Articles - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-background p-4 text-on-surface sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-outline">Content // Workspace</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Articles</h1>
                <p class="mt-2 text-sm text-on-surface-variant">Manage articles, Markdown content and publishing status.</p>
            </div>
            <a href="{{ route('admin.articles.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">
                <span class="material-symbols-outlined text-lg">add</span>
                Add article
            </a>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-xl bg-surface-low p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.articles.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_auto_auto]">
                <label class="sr-only" for="article-search">Search articles</label>
                <input id="article-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Search by title or slug..." class="min-w-0 rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2 text-sm text-on-surface placeholder:text-outline focus:border-sky-400 focus:outline-none">
                <label class="sr-only" for="category-filter">Filter by category</label>
                <select id="category-filter" name="category_id" class="rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2 text-sm text-on-surface focus:border-sky-400 focus:outline-none">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="status-filter">Filter by status</label>
                <select id="status-filter" name="status" class="rounded-lg border border-surface-highest bg-surface-lowest px-3 py-2 text-sm text-on-surface focus:border-sky-400 focus:outline-none">
                    <option value="">All statuses</option>
                    @foreach ($statusFilterOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-surface-high px-4 py-2 text-sm font-medium text-on-surface transition hover:bg-surface-highest">Filter</button>
                <a href="{{ route('admin.articles.index') }}" class="rounded-lg px-4 py-2 text-center text-sm text-on-surface-variant transition hover:bg-surface-high">Clear filters</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl bg-surface-low shadow-sm">
            @if ($articles->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="material-symbols-outlined text-4xl text-sky-300">article</span>
                    <h2 class="mt-3 font-semibold">No matching articles</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">Create a new article or change the filters.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-surface-highest bg-surface-lowest text-[10px] uppercase tracking-wider text-outline">
                            <tr>
                                <th class="px-5 py-3 font-medium">Article</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Updated</th>
                                <th class="px-5 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-highest/70">
                            @foreach ($articles as $article)
                                <tr class="transition-colors hover:bg-surface">
                                    <td class="px-5 py-4">
                                        <div class="flex min-w-0 items-center gap-3">
                                            @if ($article->thumbnail)
                                                <img src="{{ $article->thumbnail->url }}" alt="" class="h-12 w-16 shrink-0 rounded-md object-cover">
                                            @else
                                                <div class="flex h-12 w-16 shrink-0 items-center justify-center rounded-md bg-surface-lowest text-outline">
                                                    <span class="material-symbols-outlined">image</span>
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-on-surface">{{ $article->title }}</p>
                                                <p class="mt-1 truncate font-mono text-xs text-outline">{{ $article->slug }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-on-surface-variant">{{ $article->category?->name ?? 'Uncategorized' }}</td>
                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-emerald-500/10 text-emerald-300' => $article->status === 'PUBLISHED',
                                            'bg-amber-500/10 text-amber-300' => $article->status === 'SCHEDULED',
                                            'bg-slate-500/10 text-slate-300' => $article->status === 'ARCHIVED',
                                            'bg-sky-500/10 text-sky-300' => in_array($article->status, ['DRAFT', 'REVIEW'], true),
                                        ])>{{ ucfirst(strtolower($article->status)) }}</span>
                                        @if ($article->status === 'SCHEDULED' && $article->scheduled_at)
                                            <p class="mt-1 whitespace-nowrap text-[11px] text-outline">{{ $article->scheduled_at->format('d/m/Y H:i') }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-on-surface-variant">{{ $article->updated_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
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
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-surface-highest px-5 py-4">
                    {{ $articles->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
