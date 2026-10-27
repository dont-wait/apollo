@extends('layouts.admin')

@section('title', 'AI Article - Apollo Admin')

@section('content')
<div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
    @include('admin.ai-weekly.nav')
    <h1 class="text-3xl font-semibold">{{ $article->title }}</h1>
    <p class="text-sm text-[#bdc8d1]">{{ $article->source->name }} · {{ $article->status }} · {{ $article->published_at?->format('d/m/Y H:i') ?? 'Không có ngày xuất bản' }}</p>
    <a class="text-[#80d4ff]" href="{{ $article->url }}" target="_blank" rel="noopener noreferrer">Mở bài nguồn</a>
    @if ($article->duplicate_of)<p>Bài trùng với #{{ $article->duplicate_of }}</p>@endif
    @if (! $article->duplicate_of && ($article->raw_content || $article->raw_summary))<form data-ai-form method="post" action="{{ route('admin.automation-runs.analyze') }}">@csrf<input type="hidden" name="article_id" value="{{ $article->id }}"><button type="submit" class="rounded border border-sky-500 px-4 py-2 text-[#80d4ff]">Phân tích lại bài này</button><p data-ai-feedback role="status" class="mt-2 text-sm"></p></form>@endif
    <section class="rounded-lg border border-[#32353b] bg-[#1b1e25] p-5"><h2 class="mb-3 text-xl font-semibold">Nội dung nguồn</h2><p class="whitespace-pre-wrap text-sm">{{ $article->raw_content ?: $article->raw_summary }}</p></section>
    @foreach ($article->analyses->sortByDesc('version') as $analysis)
        <section class="rounded-lg border border-[#32353b] bg-[#1b1e25] p-5"><h2 class="mb-3 text-xl font-semibold">Analysis v{{ $analysis->version }} · {{ $analysis->status }} @if ($analysis->is_current) · Current @endif</h2><p class="whitespace-pre-wrap text-sm">{{ $analysis->summary ?: $analysis->error_message }}</p></section>
    @endforeach
</div>
@endsection
