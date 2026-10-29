@extends('layouts.admin')

@section('title', 'Articles - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-[#87929a]">Content // Workspace</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Articles</h1>
                <p class="mt-2 text-sm text-[#bdc8d1]">Quản lý bài viết, nội dung Markdown và trạng thái xuất bản.</p>
            </div>
            <a href="{{ route('admin.articles.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400">
                <span class="material-symbols-outlined text-lg">add</span>
                Thêm bài viết
            </a>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-xl bg-[#191c21] p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.articles.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_auto_auto]">
                <label class="sr-only" for="article-search">Tìm bài viết</label>
                <input id="article-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm theo tiêu đề hoặc slug..." class="min-w-0 rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] placeholder:text-[#87929a] focus:border-sky-400 focus:outline-none">
                <label class="sr-only" for="category-filter">Lọc theo danh mục</label>
                <select id="category-filter" name="category_id" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <option value="">Tất cả danh mục</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="status-filter">Lọc theo trạng thái</label>
                <select id="status-filter" name="status" class="rounded-lg border border-[#32353b] bg-[#0b0e13] px-3 py-2 text-sm text-[#e1e2ea] focus:border-sky-400 focus:outline-none">
                    <option value="">Tất cả trạng thái</option>
                    @foreach ($statusFilterOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-[#272a30] px-4 py-2 text-sm font-medium text-[#e1e2ea] transition hover:bg-[#32353b]">Lọc</button>
                <a href="{{ route('admin.articles.index') }}" class="rounded-lg px-4 py-2 text-center text-sm text-[#bdc8d1] transition hover:bg-[#272a30]">Xóa lọc</a>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl bg-[#191c21] shadow-sm">
            @if ($articles->isEmpty())
                <div class="px-6 py-14 text-center">
                    <span class="material-symbols-outlined text-4xl text-sky-300">article</span>
                    <h2 class="mt-3 font-semibold">Chưa có bài viết phù hợp</h2>
                    <p class="mt-1 text-sm text-[#bdc8d1]">Tạo bài viết mới hoặc thay đổi bộ lọc.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-[#32353b] bg-[#0b0e13] text-[10px] uppercase tracking-wider text-[#87929a]">
                            <tr>
                                <th class="px-5 py-3 font-medium">Bài viết</th>
                                <th class="px-4 py-3 font-medium">Danh mục</th>
                                <th class="px-4 py-3 font-medium">Trạng thái</th>
                                <th class="px-4 py-3 font-medium">Cập nhật</th>
                                <th class="px-5 py-3 text-right font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#32353b]/70">
                            @foreach ($articles as $article)
                                <tr class="transition-colors hover:bg-[#1d2025]">
                                    <td class="px-5 py-4">
                                        <div class="flex min-w-0 items-center gap-3">
                                            @if ($article->thumbnail)
                                                <img src="{{ $article->thumbnail->url }}" alt="" class="h-12 w-16 shrink-0 rounded-md object-cover">
                                            @else
                                                <div class="flex h-12 w-16 shrink-0 items-center justify-center rounded-md bg-[#0b0e13] text-[#87929a]">
                                                    <span class="material-symbols-outlined">image</span>
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-[#e1e2ea]">{{ $article->title }}</p>
                                                <p class="mt-1 truncate font-mono text-xs text-[#87929a]">{{ $article->slug }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-[#bdc8d1]">{{ $article->category?->name ?? 'Chưa phân loại' }}</td>
                                    <td class="px-4 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-emerald-500/10 text-emerald-300' => $article->status === 'PUBLISHED',
                                            'bg-amber-500/10 text-amber-300' => $article->status === 'SCHEDULED',
                                            'bg-slate-500/10 text-slate-300' => $article->status === 'ARCHIVED',
                                            'bg-sky-500/10 text-sky-300' => in_array($article->status, ['DRAFT', 'REVIEW'], true),
                                        ])>{{ ucfirst(strtolower($article->status)) }}</span>
                                        @if ($article->status === 'SCHEDULED' && $article->scheduled_at)
                                            <p class="mt-1 whitespace-nowrap text-[11px] text-[#87929a]">{{ $article->scheduled_at->format('d/m/Y H:i') }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-[#bdc8d1]">{{ $article->updated_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.articles.edit', $article) }}" class="rounded-md p-2 text-[#bdc8d1] transition hover:bg-[#272a30] hover:text-sky-300" aria-label="Sửa {{ $article->title }}" title="Sửa">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </a>
                                            <form method="POST" action="{{ route('admin.articles.destroy', $article) }}" onsubmit="return confirm('Bạn có chắc muốn xóa bài viết này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md p-2 text-[#bdc8d1] transition hover:bg-red-500/10 hover:text-red-300" aria-label="Xóa {{ $article->title }}" title="Xóa">
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
                <div class="border-t border-[#32353b] px-5 py-4">
                    {{ $articles->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
