@extends('layouts.admin')

@section('title', 'Edit Article - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-[#87929a]">Content // Workspace</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Chỉnh sửa bài viết</h1>
                <p class="mt-2 text-sm text-[#bdc8d1]">{{ $article->title }}</p>
            </div>
            <a href="{{ route('admin.articles.index') }}" class="rounded-lg bg-[#272a30] px-4 py-2 text-sm text-[#e1e2ea] transition hover:bg-[#32353b]">Quay lại danh sách</a>
        </header>

        @include('admin.articles._form', [
            'article' => $article,
            'categories' => $categories,
            'tags' => $tags,
            'action' => route('admin.articles.update', $article),
            'method' => 'PUT',
        ])
    </div>
@endsection
