@extends('layouts.admin')

@section('title', 'AI Run - Apollo Admin')

@section('content')
<div x-data="aiPipeline(@js($monitorRuns), @js(route('admin.automation-runs.show', $run)))" @ai-run-submitting.window="setSubmitting($event.detail)" @ai-run-started.window="track($event.detail)" class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
    @include('admin.ai-weekly.nav')
    <h1 class="text-3xl font-semibold">Run #{{ $run->id }}</h1>
    @include('admin.ai-weekly.progress')
    <p x-text="`${runs[0].run_type} · ${statusLabel(runs[0].status)} · Lượt ${runs[0].attempt}/${runs[0].max_attempts}`"></p>
    <p class="text-sm text-[#bdc8d1]" x-text="`Bắt đầu: ${runs[0].started_at ?? 'Chưa bắt đầu'} · Kết thúc: ${runs[0].finished_at ?? 'Chưa kết thúc'}`"></p>
    <form x-cloak x-show="['FAILED', 'PARTIAL'].includes(runs[0].status) && runs[0].attempt < runs[0].max_attempts" data-ai-form data-run-type="{{ $run->run_type }}" method="post" action="{{ route('admin.automation-runs.retry', $run) }}">@csrf<button type="submit" :disabled="busy(@js($run->run_type))" class="disabled:cursor-not-allowed disabled:opacity-50 rounded bg-sky-500 px-4 py-2 font-semibold text-[#111319]">Retry</button><p data-ai-feedback role="status" class="mt-2 text-sm"></p></form>
    <pre x-text="JSON.stringify(runs[0].payload, null, 2)" class="overflow-x-auto whitespace-pre-wrap rounded-lg border border-[#32353b] bg-[#1b1e25] p-5 text-sm">{{ json_encode($run->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</div>
@endsection
