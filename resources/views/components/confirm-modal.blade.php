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
        class="w-full max-w-md rounded-xl border border-c-30343b bg-surface-low shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirmModalTitle"
    >
        <div class="border-b border-c-30343b px-5 py-4">
            <h3
                id="confirmModalTitle"
                class="text-base font-semibold text-on-surface"
            >
                {{ $title }}
            </h3>
        </div>

        <div class="px-5 py-5">
            <p id="confirmModalMessage" class="text-sm leading-6 text-c-aeb4bd">{{ $message }}</p>
        </div>

        <div
            class="flex justify-end gap-3 border-t border-c-30343b px-5 py-4"
        >
            <button
                id="confirmModalCancel"
                type="button"
                class="rounded-lg border border-c-3a3f47 px-4 py-2 text-sm font-medium text-c-c5cad1 transition hover:bg-c-252930"
                x-on:click="$dispatch('confirm-modal-cancel')"
            >
                {{ $cancelText }}
            </button>

            <button
                id="confirmModalConfirm"
                type="button"
                class="rounded-lg bg-c-80d4ff px-4 py-2 text-sm font-semibold text-c-071018 transition hover:bg-c-9addff"
                x-on:click="$dispatch('confirm-modal-confirm')"
            >
                {{ $confirmText }}
            </button>
        </div>
    </div>
</div>