<section aria-label="Tiến trình pipeline" class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-semibold">Tiến trình job</h2>
        <p class="text-xs text-[#87929a]" x-text="pollError ? 'Mất kết nối cập nhật. Đang thử lại…' : 'Tự cập nhật mỗi 3 giây'"></p>
    </div>
    <p x-show="runs.length === 0" class="text-sm text-[#bdc8d1]">Chưa có job nào.</p>
    <div class="grid gap-3 lg:grid-cols-2">
        <template x-for="run in runs" :key="run.id">
            <article class="space-y-3 rounded-lg border border-[#32353b] bg-[#1b1e25] p-4" :aria-busy="isActive(run)">
                <div class="flex items-center justify-between gap-3">
                    <a :href="run.url" class="font-medium text-sky-300" x-text="`#${run.id} · ${typeLabel(run.run_type)}`"></a>
                    <span class="rounded bg-[#272a30] px-2 py-1 text-xs" :class="{ 'text-sky-300': isActive(run), 'text-red-300': run.status === 'FAILED', 'text-emerald-300': run.status === 'SUCCESS' }" x-text="statusLabel(run.status)"></span>
                </div>
                <p role="status" class="text-sm text-[#bdc8d1]" x-text="run.progress.message"></p>
                <progress class="h-2 w-full accent-sky-400" max="100" :value="run.progress.percent" :aria-label="`Tiến trình job #${run.id}`"></progress>
                <div class="flex flex-wrap justify-between gap-2 text-xs text-[#87929a]">
                    <span x-text="run.progress.total !== null ? `${run.progress.completed}/${run.progress.total} ${run.run_type === 'COLLECT' ? 'nguồn' : 'bài'} đã xử lý · ${run.progress.failed} lỗi` : (isActive(run) ? 'Đang xử lý, chưa có tổng số mục' : 'Đã kết thúc')"></span>
                    <span x-text="run.progress.percent !== null ? `${run.progress.percent}%` : ''"></span>
                </div>
                <p x-show="run.progress.current_item" class="break-words text-sm text-sky-200" x-text="run.progress.current_item"></p>
                <p x-show="run.error_message" role="alert" class="break-words text-sm text-red-300" x-text="run.error_message"></p>
            </article>
        </template>
    </div>
</section>
