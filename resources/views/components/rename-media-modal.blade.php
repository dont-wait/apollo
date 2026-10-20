<div
    id="renameMediaModal"
    class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/60 px-4"
    aria-hidden="true"
>
    <div
        class="w-full max-w-md rounded-xl border border-[#30343b] bg-[#191c21] shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="renameMediaModalTitle"
    >
        <div class="border-b border-[#30343b] px-5 py-4">
            <h3
                id="renameMediaModalTitle"
                class="text-base font-semibold text-[#e1e2ea]"
            >
                Rename Media
            </h3>
        </div>

        <div class="space-y-3 px-5 py-5">
            <label
                for="renameMediaInput"
                class="block text-sm font-medium text-[#c5cad1]"
            >
                Tên mới
            </label>

            <input
                id="renameMediaInput"
                type="text"
                maxlength="250"
                autocomplete="off"
                class="w-full rounded-lg border border-[#3a3f47] bg-[#111319] px-3 py-2.5 text-sm text-[#e1e2ea] outline-none transition placeholder:text-[#626a72] focus:border-[#80d4ff]"
                placeholder="Nhập tên media mới"
            >

            <p
                id="renameMediaError"
                class="hidden text-sm text-red-400"
            ></p>

            <p class="text-xs text-[#87929a]">
                Phần mở rộng file sẽ được giữ nguyên.
            </p>
        </div>

        <div
            class="flex justify-end gap-3 border-t border-[#30343b] px-5 py-4"
        >
            <button
                id="renameMediaCancel"
                type="button"
                class="rounded-lg border border-[#3a3f47] px-4 py-2 text-sm font-medium text-[#c5cad1] transition hover:bg-[#252930]"
            >
                Cancel
            </button>

            <button
                id="renameMediaSave"
                type="button"
                class="rounded-lg bg-[#80d4ff] px-4 py-2 text-sm font-semibold text-[#071018] transition hover:bg-[#9addff]"
            >
                Rename
            </button>
        </div>
    </div>
</div>