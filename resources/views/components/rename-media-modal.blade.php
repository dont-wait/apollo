<div
    id="renameMediaModal"
    class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/60 px-4"
    aria-hidden="true"
>
    <div
        class="w-full max-w-md rounded-xl border border-outline-variant bg-surface-low shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="renameMediaModalTitle"
    >
        <div class="border-b border-outline-variant px-5 py-4">
            <h3
                id="renameMediaModalTitle"
                class="text-base font-semibold text-on-surface"
            >
                Rename Media
            </h3>
        </div>

        <div class="space-y-3 px-5 py-5">
            <label
                for="renameMediaInput"
                class="block text-sm font-medium text-on-surface-variant"
            >
                Tên mới
            </label>

            <input
                id="renameMediaInput"
                type="text"
                maxlength="250"
                autocomplete="off"
                class="w-full rounded-lg border border-outline-variant bg-background px-3 py-2.5 text-sm text-on-surface outline-none transition placeholder:text-outline focus:border-primary"
                placeholder="Nhập tên media mới"
            >

            <p
                id="renameMediaError"
                class="hidden text-sm text-red-400"
            ></p>

            <p class="text-xs text-outline">
                Phần mở rộng file sẽ được giữ nguyên.
            </p>
        </div>

        <div
            class="flex justify-end gap-3 border-t border-outline-variant px-5 py-4"
        >
            <button
                id="renameMediaCancel"
                type="button"
                class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-medium text-on-surface-variant transition hover:bg-surface-high"
            >
                Cancel
            </button>

            <button
                id="renameMediaSave"
                type="button"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary transition hover:bg-primary-fixed"
            >
                Rename
            </button>
        </div>
    </div>
</div>