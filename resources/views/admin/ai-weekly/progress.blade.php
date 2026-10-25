<section aria-label="Tiến trình pipeline" class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-semibold">Tiến trình job</h2>
        <p class="text-xs text-outline" x-text="pollError ? 'Mất kết nối cập nhật. Đang thử lại…' : 'Tự cập nhật mỗi 3 giây'"></p>
    </div>
    <p x-show="runs.length === 0" class="text-sm text-on-surface-variant">Chưa có job nào.</p>
    <div class="grid gap-3 lg:grid-cols-2">
        <template x-for="run in runs" :key="run.id">
            <article class="space-y-3 rounded-lg border border-outline-variant/50 bg-surface-container-low p-4" :aria-busy="isActive(run)">
                <div class="flex items-center justify-between gap-3">
                    <a :href="run.url" class="font-medium text-primary" x-text="`#${run.id} · ${typeLabel(run.run_type)}`"></a>
                    <span class="rounded bg-surface-container-high px-2 py-1 text-xs" :class="{ 'text-primary': isActive(run), 'text-error': run.status === 'FAILED', 'text-tertiary': run.status === 'SUCCESS' }" x-text="statusLabel(run.status)"></span>
                </div>
                <p role="status" class="text-sm text-on-surface-variant" x-text="run.progress.message"></p>
                <progress class="h-2 w-full accent-primary" max="100" :value="run.progress.percent ?? (isActive(run) ? null : 0)" :aria-label="`Tiến trình job #${run.id}`"></progress>
                <div class="flex flex-wrap justify-between gap-2 text-xs text-outline">
                    <span x-text="run.progress.total !== null ? `${run.progress.completed}/${run.progress.total} ${run.run_type === 'COLLECT' ? 'nguồn' : 'bài'} đã xử lý · ${run.progress.failed} lỗi` : (isActive(run) ? 'Đang xử lý, chưa có tổng số mục' : 'Đã kết thúc')"></span>
                    <span x-text="run.progress.percent !== null ? `${run.progress.percent}%` : ''"></span>
                </div>
                <p x-show="run.progress.current_item" class="break-words text-sm text-primary" x-text="run.progress.current_item"></p>
                <p x-show="run.error_message" role="alert" class="break-words text-sm text-error" x-text="run.error_message"></p>
            </article>
        </template>
    </div>
</section>
