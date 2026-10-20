@extends('layouts.admin')

@section('title', 'AI Pipeline - Apollo Admin')

@section('content')
<div x-data="aiPipeline(@js($monitorRuns), @js(route('admin.automation-runs.index', request()->query())), @js($listing))" @ai-run-submitting.window="setSubmitting($event.detail)" @ai-run-started.window="track($event.detail)" class="-m-8 min-h-full bg-background p-4 text-on-surface sm:p-8">
    <div class="mx-auto max-w-[1440px] space-y-6">
        @include('admin.ai-weekly.nav')
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-primary">AI Automation / Operations</p>
                <h1 class="mt-2 font-display text-3xl font-semibold tracking-tight">Pipeline runs</h1>
                <p class="mt-2 text-sm leading-6 text-on-surface-variant">Khởi chạy tác vụ và theo dõi tiến trình ngay trên từng bản ghi.</p>
            </div>
            <div role="status" class="inline-flex items-center gap-2 self-start rounded-full border border-outline-variant/50 bg-surface-container-low px-3 py-2 text-xs text-on-surface-variant">
                <span class="size-1.5 rounded-full" :class="pollError ? 'bg-amber-400' : 'bg-tertiary'"></span>
                <span x-text="pollError ? 'Đang kết nối lại…' : 'Cập nhật mỗi 3 giây'">Cập nhật mỗi 3 giây</span>
            </div>
        </header>

        <section aria-label="Khởi chạy tác vụ" class="grid gap-4 md:grid-cols-2">
            @foreach ([['type' => 'COLLECT', 'route' => 'admin.automation-runs.collect', 'icon' => 'rss_feed', 'title' => 'Thu thập nguồn', 'description' => 'Lấy bài mới từ các nguồn RSS và API đang hoạt động.', 'button' => 'Thu thập ngay'], ['type' => 'ANALYZE', 'route' => 'admin.automation-runs.analyze', 'icon' => 'auto_awesome', 'title' => 'Phân tích bài viết', 'description' => 'Phân tích nội dung đã thu thập, tạo tóm tắt và phân loại chủ đề.', 'button' => 'Phân tích ngay']] as $action)
                <form data-ai-form data-run-type="{{ $action['type'] }}" method="post" action="{{ route($action['route']) }}" class="flex flex-col gap-4 rounded-lg border border-outline-variant/50 bg-surface-container-low p-5 sm:flex-row sm:items-center">
                    @csrf
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary"><span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $action['icon'] }}</span></span>
                    <div class="min-w-0 flex-1"><h2 class="text-sm font-semibold">{{ $action['title'] }}</h2><p class="mt-1 text-xs leading-5 text-outline">{{ $action['description'] }}</p><p data-ai-feedback role="status" class="mt-1 text-xs text-primary"></p></div>
                    <button type="submit" :disabled="busy(@js($action['type']))" class="inline-flex shrink-0 items-center justify-center gap-2 rounded border border-primary/30 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/20 disabled:cursor-not-allowed disabled:border-outline-variant disabled:bg-surface-container disabled:text-outline">
                        <span class="material-symbols-outlined text-base" aria-hidden="true" x-text="busy(@js($action['type'])) ? 'hourglass_top' : 'play_arrow'">play_arrow</span>
                        <span x-text="busy(@js($action['type'])) ? 'Đang có job' : @js($action['button'])">{{ $action['button'] }}</span>
                    </button>
                </form>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-lg border border-outline-variant/50 bg-surface-container-low" aria-label="Danh sách job">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/50 px-5 py-4">
                <div class="flex items-center gap-3"><span class="material-symbols-outlined text-xl text-primary" aria-hidden="true">format_list_bulleted</span><h2 class="text-sm font-semibold">Lịch sử tác vụ</h2><span class="rounded border border-outline-variant px-2 py-0.5 font-mono text-[10px] text-outline" x-text="listing.total">{{ $runs->total() }}</span></div>
                <span class="text-xs text-outline"><span class="font-mono text-primary" x-text="runs.filter(run => isActive(run)).length"></span> job đang chờ hoặc chạy</span>
            </div>
            <form method="get" class="grid items-end gap-3 border-b border-outline-variant/50 bg-surface-container-lowest/40 p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                <label class="grid gap-1.5 text-[11px] text-outline">Loại tác vụ<select name="run_type" class="w-full rounded border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs text-on-surface outline-none focus:border-primary"><option value="">Tất cả tác vụ</option>@foreach (['COLLECT' => 'Thu thập', 'ANALYZE' => 'Phân tích', 'GENERATE_REVIEW' => 'Tạo review'] as $type => $label)<option value="{{ $type }}" @selected(request('run_type') === $type)>{{ $label }}</option>@endforeach</select></label>
                <label class="grid gap-1.5 text-[11px] text-outline">Trạng thái<select name="status" class="w-full rounded border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs text-on-surface outline-none focus:border-primary"><option value="">Tất cả trạng thái</option>@foreach (['PENDING' => 'Đang chờ', 'RUNNING' => 'Đang chạy', 'SUCCESS' => 'Hoàn tất', 'PARTIAL' => 'Có lỗi một phần', 'FAILED' => 'Thất bại'] as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>@endforeach</select></label>
                <label class="grid gap-1.5 text-[11px] text-outline">Từ ngày<input type="date" name="created_from" value="{{ request('created_from') }}" class="min-w-0 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs text-on-surface outline-none focus:border-primary"></label>
                <label class="grid gap-1.5 text-[11px] text-outline">Đến ngày<input type="date" name="created_to" value="{{ request('created_to') }}" class="min-w-0 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs text-on-surface outline-none focus:border-primary"></label>
                <div class="flex gap-2"><button type="submit" class="inline-flex items-center gap-1.5 rounded bg-surface-container-high px-3 py-2 text-xs font-medium transition hover:bg-surface-container-highest"><span class="material-symbols-outlined text-base" aria-hidden="true">filter_list</span>Lọc</button><a href="{{ route('admin.automation-runs.index') }}" class="rounded px-3 py-2 text-xs text-outline transition hover:text-on-surface">Đặt lại</a></div>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead class="border-b border-outline-variant/50 bg-surface-container-lowest/40 font-mono text-[10px] uppercase tracking-wider text-outline"><tr><th scope="col" class="px-5 py-3 font-medium">Tác vụ</th><th scope="col" class="px-4 py-3 font-medium">Trạng thái</th><th scope="col" class="w-2/5 px-4 py-3 font-medium">Tiến trình</th><th scope="col" class="px-4 py-3 font-medium">Lượt chạy</th><th scope="col" class="px-5 py-3 font-medium">Thời gian tạo</th></tr></thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        <template x-for="run in listing.records" :key="run.id">
                            <tr class="align-top transition-colors hover:bg-surface-container" :class="isActive(run) ? 'bg-primary/5' : ''" :aria-busy="isActive(run)">
                                <td class="px-5 py-5"><a :href="run.url" class="group inline-flex items-start gap-3 focus-visible:outline-primary"><span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded border border-outline-variant/50 bg-surface-container-lowest text-primary"><span class="material-symbols-outlined text-base" aria-hidden="true" x-text="run.run_type === 'COLLECT' ? 'rss_feed' : 'auto_awesome'"></span></span><span><span class="block whitespace-nowrap text-xs font-medium transition group-hover:text-primary" x-text="typeLabel(run.run_type)"></span><span class="mt-1 block font-mono text-[10px] text-outline" x-text="`RUN #${run.id}`"></span></span></a></td>
                                <td class="px-4 py-5"><span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2 py-1 text-[10px]" :class="{ 'border-primary/20 bg-primary/10 text-primary': isActive(run), 'border-tertiary/20 bg-tertiary/10 text-tertiary': run.status === 'SUCCESS', 'border-error/20 bg-error/10 text-error': run.status === 'FAILED', 'border-amber-400/20 bg-amber-400/10 text-amber-300': run.status === 'PARTIAL' }"><span class="size-1.5 rounded-full bg-current" :class="run.status === 'RUNNING' ? 'motion-safe:animate-pulse' : ''"></span><span x-text="statusLabel(run.status)"></span></span></td>
                                <td class="px-4 py-5">
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-3 text-xs"><span class="text-on-surface-variant" x-text="run.progress.message"></span><span class="shrink-0 font-mono text-[10px] text-primary" x-text="run.progress.percent !== null ? `${run.progress.percent}%` : ''"></span></div>
                                        <progress class="block h-1.5 w-full overflow-hidden rounded-full accent-primary [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-primary [&::-webkit-progress-bar]:rounded-full [&::-webkit-progress-bar]:bg-surface-container-high [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-primary" max="100" :value="run.progress.percent ?? (isActive(run) ? null : 0)" :aria-label="`Tiến trình job #${run.id}`"></progress>
                                        <p class="text-[11px] text-outline" x-show="run.progress.total !== null" x-text="`${run.progress.completed}/${run.progress.total} ${run.run_type === 'COLLECT' ? 'nguồn' : 'bài'} đã xử lý${run.progress.failed ? ` · ${run.progress.failed} lỗi` : ''}`"></p>
                                        <p x-show="run.progress.current_item" class="max-w-lg break-words text-[11px] leading-5 text-primary" x-text="run.progress.current_item"></p>
                                        <p x-show="run.error_message" class="max-w-lg break-words text-[11px] leading-5 text-error" x-text="run.error_message"></p>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-5 font-mono text-xs text-on-surface-variant" x-text="`${run.attempt}/${run.max_attempts}`"></td>
                                <td class="whitespace-nowrap px-5 py-5 text-[11px] text-outline"><span x-text="run.created_at"></span><a :href="run.url" class="mt-2 inline-flex items-center gap-1 text-primary">Chi tiết<span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_outward</span></a></td>
                            </tr>
                        </template>
                        <tr x-cloak x-show="listing.records.length === 0"><td colspan="5" class="px-6 py-14 text-center"><span class="material-symbols-outlined mb-3 text-4xl text-outline" aria-hidden="true">inbox</span><p class="text-sm font-medium">Chưa có tác vụ phù hợp</p><p class="mt-2 text-xs text-outline">Khởi chạy một tác vụ hoặc điều chỉnh bộ lọc để xem kết quả.</p></td></tr>
                    </tbody>
                </table>
            </div>
            @if ($runs->hasPages())
                <div class="border-t border-outline-variant/50 p-4">{{ $runs->withQueryString()->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
