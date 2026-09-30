<div class="flex flex-col items-center gap-2 text-center">
    <div class="relative flex size-12 items-center justify-center rounded-lg bg-surface-high p-2 shadow-md">
        <svg class="size-full" viewBox="0 0 40 40" fill="none" aria-label="NeuralLog logo" role="img">
            <rect width="40" height="40" rx="10" fill="#0E131F" stroke="#232D42" stroke-width="1.5" />
            <path d="M12 28L20 12L28 28" stroke="#38BDF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M15 23H25" stroke="#6366F1" stroke-width="2" stroke-linecap="round" />
            <circle cx="20" cy="12" r="2.5" fill="#38BDF8" />
            <circle cx="12" cy="28" r="2" fill="#6366F1" />
            <circle cx="28" cy="28" r="2" fill="#6366F1" />
        </svg>
        <span class="absolute -bottom-1 -right-1 size-2.5 rounded-full bg-tertiary"></span>
    </div>

    <div class="flex flex-col items-center gap-1">
        <div class="flex items-center gap-2">
            <span class="font-display text-base font-semibold text-on-surface">NeuralLog</span>
            <span class="rounded bg-surface-highest px-1.5 py-0.5 font-mono text-[10px] font-medium tracking-wider text-primary">v2.4.0</span>
        </div>
        <h1 class="font-display text-xl font-semibold tracking-tight text-on-surface">{{ $heading }}</h1>
        <p class="max-w-xs text-sm leading-6 text-on-surface-variant">{{ $description }}</p>
    </div>
</div>
