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
        class="w-full max-w-md rounded-xl border border-outline-variant bg-surface-low shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirmModalTitle"
    >
        <div class="border-b border-outline-variant px-5 py-4">
            <h3
                id="confirmModalTitle"
                class="text-base font-semibold text-on-surface"
            >
                {{ $title }}
            </h3>
        </div>

        <div class="px-5 py-5">
            <p id="confirmModalMessage" class="text-sm leading-6 text-on-surface-variant">{{ $message }}</p>
        </div>

        <div
            class="flex justify-end gap-3 border-t border-outline-variant px-5 py-4"
        >
            <button
                id="confirmModalCancel"
                type="button"
                class="rounded-lg border border-outline-variant px-4 py-2 text-sm font-medium text-on-surface-variant transition hover:bg-surface-high"
                x-on:click="$dispatch('confirm-modal-cancel')"
            >
                {{ $cancelText }}
            </button>

            <button
                id="confirmModalConfirm"
                type="button"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary transition hover:bg-primary-fixed"
                x-on:click="$dispatch('confirm-modal-confirm')"
            >
                {{ $confirmText }}
            </button>
        </div>
    </div>
</div>