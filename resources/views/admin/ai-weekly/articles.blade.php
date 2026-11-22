@extends('layouts.admin')

@section('title', 'AI Articles - Apollo Admin')

@section('content')
<div class="-m-8 min-h-full space-y-6 bg-background p-4 text-on-surface sm:p-8">
    @include('admin.ai-weekly.nav')
    <h1 class="text-3xl font-semibold">Bài đã thu thập</h1>
    <div class="overflow-x-auto rounded-lg border border-surface-highest">
        <table class="w-full text-left text-sm"><thead class="bg-c-1b1e25 text-on-surface-variant"><tr><th class="p-3">Bài</th><th class="p-3">Nguồn</th><th class="p-3">Trạng thái</th><th class="p-3">Ngày</th></tr></thead><tbody>
            @foreach ($articles as $article)
                <tr class="border-t border-surface-highest"><td class="p-3"><a class="text-c-80d4ff" href="{{ route('admin.ai-articles.show', $article) }}">{{ $article->title }}</a></td><td class="p-3">{{ $article->source->name }}</td><td class="p-3">{{ $article->duplicate_of ? 'DUPLICATE' : $article->status }}</td><td class="p-3">{{ $article->published_at?->format('d/m/Y') ?? $article->collected_at?->format('d/m/Y') }}</td></tr>
            @endforeach
        </tbody></table>
    </div>
    {{ $articles->links() }}
</div>
@endsection
