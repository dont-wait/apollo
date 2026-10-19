@props([
    'title' => 'Confirm Action',
    'message' => '',
    'cancelText' => 'Cancel',
    'confirmText' => 'Confirm',
    'initiallyHidden' => true,
])

<div
    id="confirmModal"
    @class([
        'fixed inset-0 z-[100] items-center justify-center bg-black/60 px-4',
        'hidden' => $initiallyHidden,
        'flex' => ! $initiallyHidden,
    ])
    aria-hidden="true"
    {{ $attributes }}
>
    <div
        class="w-full max-w-md rounded-xl border border-[#30343b] bg-[#191c21] shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirmModalTitle"
    >
        <div class="border-b border-[#30343b] px-5 py-4">
            <h3
                id="confirmModalTitle"
                class="text-base font-semibold text-[#e1e2ea]"
            >
                {{ $title }}
            </h3>
        </div>

        <div class="px-5 py-5">
            <p id="confirmModalMessage" class="text-sm leading-6 text-[#aeb4bd]">{{ $message }}</p>
        </div>

        <div
            class="flex justify-end gap-3 border-t border-[#30343b] px-5 py-4"
        >
            <button
                id="confirmModalCancel"
                type="button"
                class="rounded-lg border border-[#3a3f47] px-4 py-2 text-sm font-medium text-[#c5cad1] transition hover:bg-[#252930]"
                x-on:click="$dispatch('confirm-modal-cancel')"
            >
                {{ $cancelText }}
            </button>

            <button
                id="confirmModalConfirm"
                type="button"
                class="rounded-lg bg-[#80d4ff] px-4 py-2 text-sm font-semibold text-[#071018] transition hover:bg-[#9addff]"
                x-on:click="$dispatch('confirm-modal-confirm')"
            >
                {{ $confirmText }}
            </button>
        </div>
    </div>
</div>