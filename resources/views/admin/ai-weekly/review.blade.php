@extends('layouts.admin')

@section('title', 'AI Weekly Review - Apollo Admin')

@section('content')
@php
    $isEditable = in_array($review->status, ['DRAFT', 'REVIEW'], true);
    $sources = $review->sourceArticles->sortBy('pivot.citation_order');
    $candidate = $candidateRun?->payload['candidate'] ?? null;
    $snapshot = $review->generationRun?->payload['snapshot'] ?? null;
@endphp
<div class="-m-8 min-h-screen bg-[#111319] text-[#e1e2ea]">
    <div class="border-b border-[#3e484f]/50 bg-[#191c21] px-4 py-6 sm:px-8 lg:px-10">
        <div class="mx-auto max-w-[1440px] space-y-5">
            @include('admin.ai-weekly.nav')
            <div class="flex flex-col justify-between gap-6 xl:flex-row xl:items-start">
                <div class="space-y-3">
                    <a href="{{ route('admin.weekly-reviews.index') }}" class="inline-flex items-center gap-1 font-label-sm text-label-sm text-[#8ed5ff] hover:text-white"><span class="material-symbols-outlined text-base">arrow_back</span> Danh sách review</a>
                    <div>
                        <p class="font-label-sm text-label-sm uppercase tracking-widest text-[#87929a]">Biên tập bản tin AI</p>
                        <h1 class="font-headline-sm text-2xl font-semibold tracking-tight sm:text-3xl">Review tuần {{ $review->week_start->format('d/m/Y') }}</h1>
                        <p class="mt-1 text-sm text-[#bdc8d1]">{{ $review->title }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 font-label-sm text-label-sm">
                        <span @class([
                            'inline-flex items-center gap-2 rounded border px-2 py-1 uppercase tracking-wide',
                            'border-amber-500/30 bg-amber-500/10 text-amber-300' => $review->status === 'REVIEW',
                            'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' => $review->status === 'CONVERTED',
                            'border-[#3e484f] bg-[#272a30] text-[#bdc8d1]' => ! in_array($review->status, ['REVIEW', 'CONVERTED'], true),
                        ])><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $review->status }}</span>
                        <span class="text-[#8ed5ff]">REVISION // {{ $review->revision }}</span>
                        <span class="text-[#87929a]">{{ $review->week_start->format('d/m') }} – {{ $review->week_end->format('d/m/Y') }}</span>
                        <span class="text-[#87929a]">{{ $sources->count() }} nguồn trích dẫn</span>
                    </div>
                </div>
                @if ($isEditable)
                    <div class="flex flex-wrap gap-2 xl:max-w-sm xl:justify-end">
                        <button form="weekly-review-editor" type="submit" class="inline-flex min-h-9 items-center gap-2 rounded border border-[#3e484f] bg-[#272a30] px-3 text-sm font-medium hover:border-[#8ed5ff]/50 hover:bg-[#32353b]"><span class="material-symbols-outlined text-base">save</span>Lưu bản nháp</button>
                        <form data-ai-form data-reload="true" method="post" action="{{ route('admin.weekly-reviews.convert', $review) }}" class="flex flex-col gap-1">
                            @csrf
                            <button type="submit" class="inline-flex min-h-9 items-center gap-2 rounded bg-[#38bdf8] px-3 text-sm font-semibold text-[#00354a] hover:bg-[#8ed5ff]"><span class="material-symbols-outlined text-base">article</span>Chuyển thành Post draft</button>
                            <span data-ai-feedback role="status" class="max-w-56 text-xs text-[#bdc8d1]"></span>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="mx-auto grid max-w-[1440px] gap-6 px-4 py-6 sm:px-8 lg:grid-cols-[minmax(0,1.55fr)_minmax(320px,1fr)] lg:px-10">
        <div class="min-w-0 space-y-6">
            @if ($review->quality_warning)
                <div role="alert" class="flex gap-3 rounded border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200"><span class="material-symbols-outlined text-lg">warning</span><span>{{ $review->quality_warning }}</span></div>
            @endif

            <section class="rounded border border-[#3e484f]/50 bg-[#1d2025]">
                <div class="flex items-center gap-3 border-b border-[#3e484f]/50 px-5 py-4"><span class="material-symbols-outlined text-xl text-[#8ed5ff]">edit_note</span><div><h2 class="font-headline-sm text-lg font-semibold">Bản thảo tổng hợp</h2><p class="font-label-sm text-label-sm text-[#87929a]">Chỉnh sửa Markdown và lưu trước khi chuyển sang bài viết.</p></div></div>
                @if ($isEditable)
                    <form id="weekly-review-editor" data-ai-form data-method="PUT" data-reload="true" method="post" action="{{ route('admin.weekly-reviews.update', $review) }}" class="grid gap-4 p-5">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $review->revision }}">
                        <label class="grid gap-1.5 text-sm font-medium">Tiêu đề<input name="title" maxlength="255" required value="{{ $review->title }}" class="w-full rounded border border-[#3e484f] bg-[#111319] px-3 py-2 text-[#e1e2ea] outline-none focus:border-[#38bdf8]"></label>
                        <label class="grid gap-1.5 text-sm font-medium">Tóm tắt<textarea name="excerpt" maxlength="500" rows="2" class="w-full rounded border border-[#3e484f] bg-[#111319] px-3 py-2 text-[#e1e2ea] outline-none focus:border-[#38bdf8]">{{ $review->excerpt }}</textarea></label>
                        <label class="grid gap-1.5 text-sm font-medium">Nội dung Markdown<textarea name="markdown_content" rows="22" required class="w-full rounded border border-[#3e484f] bg-[#0b0e13] px-4 py-3 font-mono text-sm leading-7 text-[#e1e2ea] outline-none focus:border-[#38bdf8]">{{ $review->markdown_content }}</textarea></label>
                        <div class="flex flex-wrap items-center gap-3"><button type="submit" class="inline-flex min-h-9 items-center gap-2 rounded bg-[#38bdf8] px-4 text-sm font-semibold text-[#00354a] hover:bg-[#8ed5ff]"><span class="material-symbols-outlined text-base">save</span>Lưu chỉnh sửa</button><span data-ai-feedback role="status" class="text-sm text-[#bdc8d1]"></span></div>
                    </form>
                @else
                    <p class="p-5 text-sm text-[#bdc8d1]">Review đã được chuyển thành bài viết. Nội dung hiện ở chế độ chỉ đọc.</p>
                @endif
            </section>

            @if ($isEditable)
                <section class="rounded border border-[#3e484f]/50 bg-[#1d2025] p-5">
                    <div class="mb-4 flex items-center gap-3"><span class="material-symbols-outlined text-xl text-[#8ed5ff]">autorenew</span><h2 class="font-headline-sm text-lg font-semibold">Tạo lại nội dung</h2></div>
                    <form data-ai-form method="post" action="{{ route('admin.weekly-reviews.regenerate', $review) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $review->revision }}">
                        <label class="grid min-w-56 flex-1 gap-1.5 text-sm">Phạm vi<select name="section" class="min-h-9 rounded border border-[#3e484f] bg-[#111319] px-3 text-[#e1e2ea] focus:border-[#38bdf8]"><option value="full">Toàn bài</option>@foreach (\App\AiWeekly\SectionEditor::HEADINGS as $key => $heading)<option value="{{ $key }}">{{ substr($heading, 3) }}</option>@endforeach</select></label>
                        <button type="submit" class="min-h-9 rounded border border-[#38bdf8]/50 px-4 text-sm font-medium text-[#8ed5ff] hover:bg-[#38bdf8]/10">Tạo candidate</button>
                        <span data-ai-feedback role="status" class="w-full text-sm text-[#bdc8d1]"></span>
                    </form>
                </section>
                @if ($candidate && ($candidate['revision'] ?? null) === $review->revision)
                    <section class="space-y-4 rounded border border-[#38bdf8]/40 bg-[#1d2025] p-5">
                        <div class="flex items-center gap-2"><span class="material-symbols-outlined text-[#8ed5ff]">auto_awesome</span><h2 class="font-headline-sm text-lg font-semibold">Candidate từ run #{{ $candidateRun->id }}</h2></div>
                        <p class="font-medium">{{ $candidate['draft']['title'] }}</p>
                        <pre class="max-h-80 overflow-auto whitespace-pre-wrap rounded bg-[#0b0e13] p-4 font-mono text-xs leading-6 text-[#bdc8d1]">{{ $candidate['draft']['markdown_content'] }}</pre>
                        <form data-ai-form data-reload="true" method="post" action="{{ route('admin.weekly-reviews.apply-candidate', $review) }}" class="flex flex-wrap items-center gap-3">
                            @csrf
                            <input type="hidden" name="run_id" value="{{ $candidateRun->id }}"><input type="hidden" name="revision" value="{{ $review->revision }}">
                            <button type="submit" class="min-h-9 rounded bg-[#38bdf8] px-4 text-sm font-semibold text-[#00354a] hover:bg-[#8ed5ff]">Áp dụng candidate</button>
                            <span data-ai-feedback role="status" class="text-sm text-[#bdc8d1]"></span>
                        </form>
                    </section>
                @endif
            @endif

            <section class="rounded border border-[#3e484f]/50 bg-[#1d2025]">
                <div class="flex items-center gap-3 border-b border-[#3e484f]/50 px-5 py-4"><span class="material-symbols-outlined text-xl text-[#8ed5ff]">preview</span><div><h2 class="font-headline-sm text-lg font-semibold">Xem trước bài viết</h2><p class="font-label-sm text-label-sm text-[#87929a]">Bản đã lưu · revision {{ $review->revision }}</p></div></div>
                <div class="ai-weekly-preview space-y-4 p-5 text-sm leading-7 text-[#e1e2ea]">{!! $previewHtml !!}</div>
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="rounded border border-[#3e484f]/50 bg-[#1d2025] p-5">
                <div class="flex items-center justify-between gap-3"><h2 class="flex items-center gap-2 font-headline-sm text-lg font-semibold"><span class="material-symbols-outlined text-xl text-emerald-400">fact_check</span>Thông tin bản thảo</h2><span class="font-label-sm text-label-sm text-emerald-300">{{ $review->status }}</span></div>
                <dl class="mt-5 grid grid-cols-2 gap-3 font-label-sm text-label-sm">
                    <div class="rounded bg-[#191c21] p-3"><dt class="text-[#87929a]">Nguồn trích dẫn</dt><dd class="mt-1 text-2xl text-emerald-300">{{ $sources->count() }}</dd></div>
                    <div class="rounded bg-[#191c21] p-3"><dt class="text-[#87929a]">Revision</dt><dd class="mt-1 text-2xl text-[#8ed5ff]">{{ $review->revision }}</dd></div>
                    <div class="col-span-2 flex justify-between gap-3 border-t border-[#3e484f]/50 pt-3"><dt class="text-[#87929a]">Model</dt><dd class="break-all text-right">{{ $review->model_name ?? 'Chưa ghi nhận' }}</dd></div>
                    <div class="col-span-2 flex justify-between gap-3"><dt class="text-[#87929a]">Run tạo bản thảo</dt><dd>@if ($review->generationRun)<a href="{{ route('admin.automation-runs.show', $review->generationRun) }}" class="text-[#8ed5ff] hover:underline">#{{ $review->generationRun->id }}</a>@else — @endif</dd></div>
                    <div class="col-span-2 flex justify-between gap-3"><dt class="text-[#87929a]">Cập nhật</dt><dd>{{ $review->reviewed_at?->format('d/m/Y H:i') ?? $review->generated_at?->format('d/m/Y H:i') ?? 'Chưa ghi nhận' }}</dd></div>
                </dl>
            </section>

            <section class="rounded border border-[#3e484f]/50 bg-[#1d2025] p-5">
                <div class="flex items-center justify-between gap-3"><h2 class="flex items-center gap-2 font-headline-sm text-lg font-semibold"><span class="material-symbols-outlined text-xl text-[#8ed5ff]">menu_book</span>Nguồn trích dẫn</h2><span class="font-label-sm text-label-sm text-[#87929a]">{{ $sources->count() }} bài</span></div>
                <div class="mt-4 grid gap-3">
                    @forelse ($sources as $article)
                        <article class="rounded border border-[#3e484f]/40 bg-[#191c21] p-3.5">
                            <div class="mb-2 flex items-center justify-between gap-2 font-label-sm text-label-sm"><span class="rounded bg-[#272a30] px-2 py-1 text-[#8ed5ff]">#{{ $article->pivot->citation_order }}</span><span class="truncate text-[#87929a]">{{ $article->source->name }}</span></div>
                            <h3 class="font-headline-sm font-semibold leading-snug"><a href="{{ $article->url }}" target="_blank" rel="noopener noreferrer" class="hover:text-[#8ed5ff]">{{ $article->title }} <span class="material-symbols-outlined align-middle text-sm">open_in_new</span></a></h3>
                            @if ($article->analyses->first()?->summary)<p class="mt-2 line-clamp-4 text-sm leading-6 text-[#bdc8d1]">{{ $article->analyses->first()->summary }}</p>@endif
                            <a href="{{ route('admin.ai-articles.show', $article) }}" class="mt-3 inline-block font-label-sm text-label-sm text-[#8ed5ff] hover:underline">Xem bài đã thu thập</a>
                        </article>
                    @empty
                        <p class="text-sm text-[#bdc8d1]">Chưa có nguồn trích dẫn.</p>
                    @endforelse
                </div>
            </section>

            @if (! empty($snapshot['topics']))
                <section class="rounded border border-[#3e484f]/50 bg-[#1d2025] p-5"><h2 class="mb-3 font-headline-sm text-lg font-semibold">Trend snapshot</h2><pre class="overflow-x-auto whitespace-pre-wrap rounded bg-[#0b0e13] p-3 font-mono text-xs leading-6 text-[#bdc8d1]">{{ json_encode($snapshot['topics'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></section>
            @endif
        </aside>
    </div>
</div>
@endsection
