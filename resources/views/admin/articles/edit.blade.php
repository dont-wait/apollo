@extends('layouts.admin')

@section('title', 'Edit Article - Apollo Admin')

@section('content')
    <div class="-m-8 min-h-full space-y-6 bg-background p-4 text-on-surface sm:p-8">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-[11px] uppercase tracking-[0.16em] text-outline">Content // Workspace</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Edit article</h1>
                <p class="mt-2 text-sm text-on-surface-variant">{{ $article->title }}</p>
            </div>
            <a href="{{ route('admin.articles.index') }}" class="rounded-lg bg-surface-high px-4 py-2 text-sm text-on-surface transition hover:bg-surface-highest">Back to list</a>
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
