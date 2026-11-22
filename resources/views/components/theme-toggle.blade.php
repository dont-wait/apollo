<button
    type="button"
    data-theme-toggle
    {{ $attributes->class('inline-flex size-9 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary') }}
    aria-label="Chuyển giao diện sáng/tối"
    title="Chuyển giao diện sáng/tối"
>
    <svg class="theme-show-dark size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
    <svg class="theme-show-light size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
</button>
