@extends('layouts.admin')

@section('title', 'AI Run - Apollo Admin')

@section('content')
<div class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
    @include('admin.ai-weekly.nav')
    <h1 class="text-3xl font-semibold">Run #{{ $run->id }}</h1>
    <p>{{ $run->run_type }} · {{ $run->status }} · {{ $run->attempt }}/{{ $run->max_attempts }} attempts</p>
    <p class="text-sm text-[#bdc8d1]">Bắt đầu: {{ $run->started_at?->format('d/m/Y H:i') ?? 'Chưa bắt đầu' }} · Kết thúc: {{ $run->finished_at?->format('d/m/Y H:i') ?? 'Chưa kết thúc' }}</p>
    @if ($run->error_message)<p role="alert" class="rounded border border-red-500/40 p-3 text-red-300">{{ $run->error_message }}</p>@endif
    @if (in_array($run->status, ['FAILED', 'PARTIAL']) && $run->attempt < $run->max_attempts)
        <form data-ai-form data-reload="true" method="post" action="{{ route('admin.automation-runs.retry', $run) }}">@csrf<button type="submit" class="rounded bg-sky-500 px-4 py-2 font-semibold text-[#111319]">Retry</button><p data-ai-feedback role="status" class="mt-2 text-sm"></p></form>
    @endif
    <pre class="overflow-x-auto whitespace-pre-wrap rounded-lg border border-[#32353b] bg-[#1b1e25] p-5 text-sm">{{ json_encode($run->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</div>
@endsection
