@extends('layouts.admin')

@section('title', 'AI Run - Apollo Admin')

@section('content')
<div x-data="aiPipeline(@js($monitorRuns), @js(route('admin.automation-runs.show', $run)))" @ai-run-submitting.window="setSubmitting($event.detail)" @ai-run-started.window="track($event.detail)" class="-m-8 min-h-full bg-background p-4 text-on-surface sm:p-8">
    <div class="mx-auto max-w-[1440px] space-y-6">
        @include('admin.ai-weekly.nav')
        <a href="{{ route('admin.automation-runs.index') }}" class="inline-flex items-center gap-2 text-xs text-outline transition hover:text-primary"><span class="material-symbols-outlined text-base" aria-hidden="true">arrow_back</span>Danh sách tác vụ</a>
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="font-mono text-[10px] uppercase tracking-[0.18em] text-primary">AI Automation / Run detail</p><h1 class="mt-2 font-display text-3xl font-semibold tracking-tight">Run #{{ $run->id }}</h1><p class="mt-2 text-sm text-on-surface-variant" x-text="`${typeLabel(runs[0].run_type)} · Lượt ${runs[0].attempt}/${runs[0].max_attempts}`"></p></div>
            <form x-cloak x-show="['FAILED', 'PARTIAL'].includes(runs[0].status) && runs[0].attempt < runs[0].max_attempts" data-ai-form data-run-type="{{ $run->run_type }}" method="post" action="{{ route('admin.automation-runs.retry', $run) }}" class="space-y-2">
                @csrf
                <button type="submit" :disabled="busy(@js($run->run_type))" class="inline-flex items-center gap-2 rounded bg-primary-container px-4 py-2.5 text-sm font-semibold text-on-primary transition hover:bg-primary disabled:cursor-not-allowed disabled:opacity-50"><span class="material-symbols-outlined text-base" aria-hidden="true">replay</span>Thử lại tác vụ</button>
                <p data-ai-feedback role="status" class="text-xs text-on-surface-variant"></p>
            </form>
        </header>
        <div class="grid gap-3 rounded-lg border border-outline-variant/50 bg-surface-container-low p-5 text-xs sm:grid-cols-2">
            <div><p class="font-mono text-[10px] uppercase tracking-wider text-outline">Bắt đầu</p><p class="mt-2 text-on-surface-variant" x-text="runs[0].started_at ?? 'Đang chờ worker'"></p></div>
            <div><p class="font-mono text-[10px] uppercase tracking-wider text-outline">Kết thúc</p><p class="mt-2 text-on-surface-variant" x-text="runs[0].finished_at ?? 'Chưa kết thúc'"></p></div>
        </div>
        @include('admin.ai-weekly.progress')
        <details class="group rounded-lg border border-outline-variant/50 bg-surface-container-low">
            <summary class="flex cursor-pointer list-none items-center justify-between p-5 text-sm font-medium [&::-webkit-details-marker]:hidden">Dữ liệu xử lý<span class="material-symbols-outlined text-lg text-outline transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span></summary>
            <pre x-text="JSON.stringify(runs[0].payload, null, 2)" class="max-h-[36rem] overflow-auto whitespace-pre-wrap break-words border-t border-outline-variant/50 bg-surface-container-lowest p-5 text-xs leading-6 text-on-surface-variant">{{ json_encode($run->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    </div>
</div>
@endsection
