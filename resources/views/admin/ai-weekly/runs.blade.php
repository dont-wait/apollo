@extends('layouts.admin')

@section('title', 'AI Pipeline - Apollo Admin')

@section('content')
<div x-data="aiPipeline(@js($monitorRuns), @js($pollUrl), @js($listing))" @ai-run-submitting.window="setSubmitting($event.detail)" @ai-run-started.window="track($event.detail)" data-csrf="{{ csrf_token() }}" class="-m-8 min-h-full bg-background p-4 text-on-surface sm:p-8">
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
            @foreach ([['type' => 'COLLECT', 'route' => 'admin.automation-runs.collect', 'icon' => 'rss_feed', 'title' => 'Thu thập nguồn', 'description' => 'Lấy bài mới từ các nguồn RSS và API đang hoạt động.', 'button' => 'Thu thập ngay'], ['type' => 'ANALYZE', 'route' => 'admin.automation-runs.analyze', 'icon' => 'auto_awesome', 'title' => 'Phân tích bài viết', 'description' => 'Chọn các bài đã thu thập để tóm tắt và phân loại chủ đề.', 'button' => 'Chọn bài để phân tích']] as $action)
                <form data-ai-form data-run-type="{{ $action['type'] }}" method="post" action="{{ route($action['route']) }}" class="flex flex-col gap-4 rounded-lg border border-outline-variant/50 bg-surface-container-low p-5 sm:flex-row sm:items-center">
                    @csrf
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary"><span class="material-symbols-outlined text-2xl" aria-hidden="true">{{ $action['icon'] }}</span></span>
                    <div class="min-w-0 flex-1"><h2 class="text-sm font-semibold">{{ $action['title'] }}</h2><p class="mt-1 text-xs leading-5 text-outline">{{ $action['description'] }}</p><p data-ai-feedback role="status" class="mt-1 text-xs text-primary"></p></div>
                    <button type="{{ $action['type'] === 'ANALYZE' ? 'button' : 'submit' }}" @if ($action['type'] === 'ANALYZE') @click="$dispatch('open-analyze-picker')" @endif :disabled="busy(@js($action['type']))" class="inline-flex shrink-0 items-center justify-center gap-2 rounded border border-primary/30 bg-primary/10 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/20 disabled:cursor-not-allowed disabled:border-outline-variant disabled:bg-surface-container disabled:text-outline">
                        <span class="material-symbols-outlined text-base" aria-hidden="true" x-text="busy(@js($action['type'])) ? 'hourglass_top' : 'play_arrow'">play_arrow</span>
                        <span x-text="busy(@js($action['type'])) ? 'Đang có job' : @js($action['button'])">{{ $action['button'] }}</span>
                    </button>
                </form>
            @endforeach
        </section>


        <div x-data="analyzePicker({ listUrl: @js(route('admin.ai-articles.index')), analyzeUrl: @js(route('admin.automation-runs.analyze')), csrf: @js(csrf_token()) })" @open-analyze-picker.window="openPicker()" @keydown.escape.window="close()">
            <div x-cloak x-show="open" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4" @click.self="close()" role="dialog" aria-modal="true" aria-labelledby="analyze-picker-title">
                <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-outline-variant/50 bg-surface-container-low shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-outline-variant/50 px-5 py-4">
                        <div>
                            <h2 id="analyze-picker-title" class="text-base font-semibold">Chọn bài để phân tích</h2>
                            <p class="mt-1 text-xs text-outline">Chỉ những bài bạn tick mới được đưa vào job. Bài chưa chọn sẽ không bị đụng tới.</p>
                        </div>
                        <button type="button" @click="close()" class="rounded p-1 text-outline transition hover:bg-surface-container-high hover:text-on-surface" aria-label="Đóng"><span class="material-symbols-outlined text-xl" aria-hidden="true">close</span></button>
                    </div>

                    <div class="space-y-3 border-b border-outline-variant/50 px-5 py-3">
                        <div class="flex items-center gap-2 rounded border border-outline-variant bg-surface-container-lowest px-3 py-2">
                            <span class="material-symbols-outlined text-lg text-outline" aria-hidden="true">search</span>
                            <input type="search" x-model="query" @input="search()" placeholder="Tìm theo tiêu đề..." class="w-full bg-transparent text-sm text-on-surface outline-none placeholder:text-outline">
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="inline-flex rounded border border-outline-variant p-0.5">
                                <button type="button" @click="setFilter('pending')" :class="filter === 'pending' ? 'bg-surface-container-high text-on-surface' : 'text-outline hover:text-on-surface'" class="rounded px-3 py-1 font-medium transition">Chưa phân tích</button>
                                <button type="button" @click="setFilter('all')" :class="filter === 'all' ? 'bg-surface-container-high text-on-surface' : 'text-outline hover:text-on-surface'" class="rounded px-3 py-1 font-medium transition">Tất cả</button>
                            </div>
                            <div class="flex items-center gap-3 text-outline">
                                <span><span class="font-mono text-on-surface" x-text="total"></span> bài</span>
                                <button type="button" @click="selectVisible()" class="text-primary hover:underline">Chọn các bài đang hiện</button>
                                <button type="button" @click="clearSelection()" x-show="count > 0" class="hover:text-on-surface">Bỏ chọn</button>
                            </div>
                        </div>
                    </div>

                    <ul class="min-h-[12rem] flex-1 divide-y divide-outline-variant/40 overflow-y-auto">
                        <template x-for="item in items" :key="item.id">
                            <li>
                                <label class="flex items-start gap-3 px-5 py-3 transition" :class="item.selectable ? 'cursor-pointer hover:bg-surface-container' : 'cursor-not-allowed opacity-50'">
                                    <input type="checkbox" class="mt-0.5 size-4 rounded border-outline-variant bg-surface-container-lowest text-sky-400 focus:ring-sky-400" :checked="isSelected(item)" :disabled="! item.selectable" @change="toggle(item)">
                                    <span class="min-w-0 flex-1">
                                        <span class="line-clamp-2 text-sm text-on-surface" x-text="item.title"></span>
                                        <span class="mt-1 flex flex-wrap items-center gap-x-2 text-[11px] text-outline">
                                            <span x-text="item.source"></span><span aria-hidden="true">·</span><span x-text="item.date"></span><span aria-hidden="true">·</span>
                                            <span class="font-mono uppercase" x-text="item.status"></span>
                                            <template x-if="item.reason"><span class="text-amber-300" x-text="'(' + item.reason + ')'"></span></template>
                                        </span>
                                    </span>
                                </label>
                            </li>
                        </template>
                        <li x-show="loading" class="px-5 py-6 text-center text-xs text-outline">Đang tải…</li>
                        <li x-show="! loading && items.length === 0 && ! error" class="px-5 py-10 text-center text-sm text-outline">Không có bài phù hợp.</li>
                        <li x-show="nextPage && ! loading" class="px-5 py-3 text-center"><button type="button" @click="load()" class="text-xs text-primary hover:underline">Tải thêm</button></li>
                    </ul>

                    <div class="space-y-3 border-t border-outline-variant/50 bg-surface-container-lowest/40 px-5 py-4">
                        <p x-show="error" x-text="error" role="alert" class="text-xs text-red-300"></p>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <button type="button" @click="submitAllPending()" :disabled="submitting" class="text-xs text-outline transition hover:text-on-surface disabled:opacity-50">Phân tích tất cả bài chưa phân tích…</button>
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-xs text-on-surface-variant"><span x-text="count"></span> đã chọn</span>
                                <button type="button" @click="submitSelected()" :disabled="count === 0 || submitting" class="inline-flex items-center gap-2 rounded border border-primary/30 bg-primary/10 px-4 py-2 text-xs font-semibold text-primary transition hover:bg-primary/20 disabled:cursor-not-allowed disabled:border-outline-variant disabled:bg-surface-container disabled:text-outline">
                                    <span class="material-symbols-outlined text-base" aria-hidden="true" x-text="submitting ? 'hourglass_top' : 'auto_awesome'">auto_awesome</span>
                                    <span x-text="submitting ? 'Đang tạo job…' : 'Phân tích ' + count + ' bài'">Phân tích bài đã chọn</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="overflow-hidden rounded-lg border border-outline-variant/50 bg-surface-container-low" aria-label="Danh sách job">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant/50 px-5 py-4">
                <div class="flex items-center gap-3"><span class="material-symbols-outlined text-xl text-primary" aria-hidden="true">format_list_bulleted</span><h2 class="text-sm font-semibold">Lịch sử tác vụ</h2><span class="rounded border border-outline-variant px-2 py-0.5 font-mono text-[10px] text-outline" x-text="listing.total">{{ $runs->total() }}</span></div>
                <span class="text-xs text-outline"><span class="font-mono text-primary" x-text="runs.filter(run => isActive(run)).length"></span> job đang chờ hoặc chạy</span>
            </div>
            <p x-cloak x-show="actionError" x-text="actionError" role="alert" class="border-b border-outline-variant/50 bg-error/10 px-5 py-2 text-xs text-error"></p>
            <form method="get" class="grid items-end gap-3 border-b border-outline-variant/50 bg-surface-container-lowest/40 p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                <label class="grid gap-1.5 text-[11px] text-outline">Loại tác vụ<select name="run_type" class="w-full rounded border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs text-on-surface outline-none focus:border-primary"><option value="">Tất cả tác vụ</option>@foreach (['COLLECT' => 'Thu thập', 'ANALYZE' => 'Phân tích', 'GENERATE_REVIEW' => 'Tạo review'] as $type => $label)<option value="{{ $type }}" @selected(request('run_type') === $type)>{{ $label }}</option>@endforeach</select></label>
                <label class="grid gap-1.5 text-[11px] text-outline">Trạng thái<select name="status" class="w-full rounded border border-outline-variant bg-surface-container-lowest px-3 py-2 text-xs text-on-surface outline-none focus:border-primary"><option value="">Tất cả trạng thái</option>@foreach (['PENDING' => 'Đang chờ', 'RUNNING' => 'Đang chạy', 'PAUSED' => 'Đã hoãn', 'SUCCESS' => 'Hoàn tất', 'PARTIAL' => 'Có lỗi một phần', 'FAILED' => 'Thất bại', 'CANCELLED' => 'Đã hủy'] as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>@endforeach</select></label>
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
                                <td class="px-4 py-5"><span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2 py-1 text-[10px]" :class="{ 'border-primary/20 bg-primary/10 text-primary': isActive(run), 'border-tertiary/20 bg-tertiary/10 text-tertiary': run.status === 'SUCCESS', 'border-error/20 bg-error/10 text-error': run.status === 'FAILED', 'border-amber-400/20 bg-amber-400/10 text-amber-300': run.status === 'PARTIAL' || run.status === 'PAUSED', 'border-outline-variant bg-surface-container-high text-outline': run.status === 'CANCELLED' }"><span class="size-1.5 rounded-full bg-current" :class="run.status === 'RUNNING' ? 'motion-safe:animate-pulse' : ''"></span><span x-text="statusLabel(run.status)"></span></span></td>
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
                                <td class="whitespace-nowrap px-5 py-5 text-[11px] text-outline"><span class="block" x-text="run.created_at"></span><a :href="run.url" class="mt-2 inline-flex items-center gap-1 text-primary">Chi tiết<span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_outward</span></a><div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <button type="button" x-show="canPause(run)" :disabled="acting[run.id]" @click="act(run, 'pause')" class="inline-flex items-center gap-1 rounded border border-amber-400/30 px-2 py-1 text-[11px] font-medium text-amber-300 transition hover:bg-amber-400/10 disabled:opacity-50"><span class="material-symbols-outlined text-sm" aria-hidden="true">pause</span>Hoãn</button>
                                    <button type="button" x-show="canResume(run)" :disabled="acting[run.id]" @click="act(run, 'resume')" class="inline-flex items-center gap-1 rounded border border-primary/30 px-2 py-1 text-[11px] font-medium text-primary transition hover:bg-primary/10 disabled:opacity-50"><span class="material-symbols-outlined text-sm" aria-hidden="true">play_arrow</span>Tiếp tục</button>
                                    <button type="button" x-show="canCancel(run)" :disabled="acting[run.id]" @click="act(run, 'cancel')" class="inline-flex items-center gap-1 rounded border border-error/30 px-2 py-1 text-[11px] font-medium text-error transition hover:bg-error/10 disabled:opacity-50"><span class="material-symbols-outlined text-sm" aria-hidden="true">cancel</span>Hủy</button>
                                </div></td>
                            </tr>
                        </template>
                        <tr x-cloak x-show="listing.records.length === 0"><td colspan="5" class="px-6 py-14 text-center"><span class="material-symbols-outlined mb-3 text-4xl text-outline" aria-hidden="true">inbox</span><p class="text-sm font-medium">Chưa có tác vụ phù hợp</p><p class="mt-2 text-xs text-outline">Khởi chạy một tác vụ hoặc điều chỉnh bộ lọc để xem kết quả.</p></td></tr>
                    </tbody>
                </table>
            </div>
            <nav x-cloak x-show="listing.last_page > 1" aria-label="Phân trang lịch sử tác vụ" class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant/50 p-4 text-xs">
                <span class="text-outline" x-text="`Trang ${listing.page} / ${listing.last_page} · ${listing.total} tác vụ`"></span>
                <div class="flex items-center gap-2">
                    <template x-if="listing.page > 1"><a :href="pageUrl(listing.page - 1)" rel="prev" class="rounded border border-outline-variant px-3 py-2 text-on-surface-variant transition hover:border-primary hover:text-primary">Trang trước</a></template>
                    <template x-if="listing.page < listing.last_page"><a :href="pageUrl(listing.page + 1)" rel="next" class="rounded border border-outline-variant px-3 py-2 text-on-surface-variant transition hover:border-primary hover:text-primary">Trang sau</a></template>
                </div>
            </nav>
        </section>
    </div>
</div>
@endsection
