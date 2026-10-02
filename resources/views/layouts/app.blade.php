<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Hanken+Grotesk:wght@600;700&family=JetBrains+Mono:wght@400;500&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background font-sans text-on-surface antialiased">
    <header
        class="border-b border-outline-variant/50 bg-surface-low"
        x-data="{ mobileMenuOpen: false }"
        @keydown.escape.window="mobileMenuOpen = false"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a class="flex shrink-0 items-center gap-2.5 text-sm font-semibold tracking-wide text-on-surface transition-colors hover:text-primary" href="{{ route('home') }}">
                <span class="flex size-8 items-center justify-center rounded-lg border border-primary/30 bg-primary/10 font-display text-sm font-bold text-primary">A</span>
                <span>{{ config('app.name', 'Apollo Blog') }}</span>
                <span class="hidden font-mono text-[9px] uppercase tracking-[0.18em] text-primary/70 sm:inline">AI &amp; Systems</span>
            </a>

            <nav class="hidden items-center gap-5 lg:flex" aria-label="Primary navigation">
                <a class="font-mono text-[11px] uppercase tracking-[0.14em] text-on-surface-variant transition-colors hover:text-primary" href="#articles">Articles</a>
                <a class="font-mono text-[11px] uppercase tracking-[0.14em] text-on-surface-variant transition-colors hover:text-primary" href="#weekly">AI Weekly</a>
                <a class="font-mono text-[11px] uppercase tracking-[0.14em] text-on-surface-variant transition-colors hover:text-primary" href="#categories">Categories</a>
                <a class="font-mono text-[11px] uppercase tracking-[0.14em] text-on-surface-variant transition-colors hover:text-primary" href="#about">About</a>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <a class="hidden items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.12em] text-on-surface-variant transition-colors hover:text-primary md:flex" href="#articles">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">search</span>
                    Search
                </a>
                @auth
                    <span class="hidden max-w-28 truncate font-mono text-[11px] uppercase tracking-[0.12em] text-tertiary xl:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="font-mono text-[11px] uppercase tracking-[0.12em] text-on-surface-variant transition-colors hover:text-primary" type="submit">Sign Out</button>
                    </form>
                @else
                    <a class="font-mono text-[11px] uppercase tracking-[0.12em] text-on-surface-variant transition-colors hover:text-primary" href="{{ route('login') }}">Sign In</a>
                    <a class="rounded-md bg-primary px-3 py-2 font-display text-xs font-semibold text-on-primary transition-colors hover:bg-surface-tint" href="{{ route('register') }}">Create Account</a>
                @endauth
            </div>

            <button
                class="rounded-md border border-outline-variant px-3 py-2 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:border-primary hover:text-primary lg:hidden"
                type="button"
                aria-controls="mobile-navigation"
                :aria-expanded="mobileMenuOpen"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <span x-show="!mobileMenuOpen">Menu</span>
                <span x-cloak x-show="mobileMenuOpen">Close</span>
            </button>
        </div>

        <nav
            id="mobile-navigation"
            class="border-t border-outline-variant/50 px-4 py-3 lg:hidden"
            x-cloak
            x-show="mobileMenuOpen"
            x-transition.origin.top
            aria-label="Mobile navigation"
        >
            <a class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-primary transition-colors hover:bg-surface-high" href="#articles" @click="mobileMenuOpen = false">Articles</a>
            <a class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" href="#weekly" @click="mobileMenuOpen = false">AI Weekly</a>
            <a class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" href="#categories" @click="mobileMenuOpen = false">Categories</a>
            <a class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" href="#about" @click="mobileMenuOpen = false">About</a>
            @auth
                <form class="px-3 py-3" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:text-primary" type="submit">Sign Out</button>
                </form>
            @else
                <a class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" href="{{ route('login') }}" @click="mobileMenuOpen = false">Sign In</a>
                <a class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" href="{{ route('register') }}" @click="mobileMenuOpen = false">Create Account</a>
            @endauth
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        @yield('content')
    </main>
</body>
</html>
