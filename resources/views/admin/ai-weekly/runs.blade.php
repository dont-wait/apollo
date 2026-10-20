@extends('layouts.admin')

@section('title', 'AI Pipeline - Apollo Admin')

@section('content')
<div x-data="aiPipeline(@js($monitorRuns), @js(route('admin.automation-runs.index')))" @ai-run-submitting.window="setSubmitting($event.detail)" @ai-run-started.window="track($event.detail)" class="-m-8 min-h-full space-y-6 bg-[#111319] p-4 text-[#e1e2ea] sm:p-8">
    @include('admin.ai-weekly.nav')
    <h1 class="text-3xl font-semibold">Pipeline runs</h1>
    <div class="flex flex-wrap gap-3">
        <form data-ai-form data-run-type="COLLECT" method="post" action="{{ route('admin.automation-runs.collect') }}">@csrf<button type="submit" :disabled="busy('COLLECT')" x-text="busy('COLLECT') ? 'Đang có job thu thập…' : 'Thu thập ngay'" class="disabled:cursor-not-allowed disabled:opacity-50 rounded bg-sky-500 px-4 py-2 font-semibold text-[#111319]">Thu thập ngay</button><p data-ai-feedback role="status" class="mt-2 text-sm"></p></form>
        <form data-ai-form data-run-type="ANALYZE" method="post" action="{{ route('admin.automation-runs.analyze') }}">@csrf<button type="submit" :disabled="busy('ANALYZE')" x-text="busy('ANALYZE') ? 'Đang có job phân tích…' : 'Phân tích ngay'" class="disabled:cursor-not-allowed disabled:opacity-50 rounded bg-sky-500 px-4 py-2 font-semibold text-[#111319]">Phân tích ngay</button><p data-ai-feedback role="status" class="mt-2 text-sm"></p></form>
    </div>
    @include('admin.ai-weekly.progress')
    <form method="get" class="flex flex-wrap gap-3"><select name="run_type" class="rounded bg-[#1b1e25] p-2"><option value="">Mọi loại</option>@foreach (['COLLECT', 'ANALYZE', 'GENERATE_REVIEW'] as $type)<option value="{{ $type }}" @selected(request('run_type') === $type)>{{ $type }}</option>@endforeach</select><select name="status" class="rounded bg-[#1b1e25] p-2"><option value="">Mọi trạng thái</option>@foreach (['PENDING', 'RUNNING', 'SUCCESS', 'PARTIAL', 'FAILED'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select><input type="date" name="created_from" value="{{ request('created_from') }}" aria-label="Từ ngày" class="rounded bg-[#1b1e25] p-2"><input type="date" name="created_to" value="{{ request('created_to') }}" aria-label="Đến ngày" class="rounded bg-[#1b1e25] p-2"><button class="rounded border border-[#32353b] px-4 py-2">Lọc</button></form>
    <div class="overflow-x-auto rounded-lg border border-[#32353b]"><table class="w-full text-left text-sm"><thead class="bg-[#1b1e25] text-[#bdc8d1]"><tr><th class="p-3">Run</th><th class="p-3">Loại</th><th class="p-3">Trạng thái</th><th class="p-3">Lượt</th><th class="p-3">Bắt đầu</th></tr></thead><tbody>
        @foreach ($runs as $run)<tr class="border-t border-[#32353b]"><td class="p-3"><a class="text-[#80d4ff]" href="{{ route('admin.automation-runs.show', $run) }}">#{{ $run->id }}</a></td><td class="p-3">{{ $run->run_type }}</td><td class="p-3" x-text="runs.find(item => item.id === {{ $run->id }})?.status ?? @js($run->status)">{{ $run->status }}</td><td class="p-3">{{ $run->attempt }}/{{ $run->max_attempts }}</td><td class="p-3">{{ $run->created_at?->format('d/m/Y H:i') }}</td></tr>@endforeach
    </tbody></table></div>
    {{ $runs->links() }}
</div>
@endsection
