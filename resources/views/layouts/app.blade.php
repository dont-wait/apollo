<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background font-sans text-on-surface antialiased">
    <header
        class="border-b border-outline-variant/50 bg-surface-low"
        x-data="{ mobileMenuOpen: false }"
        @keydown.escape.window="mobileMenuOpen = false"
    >
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a class="flex items-center gap-3 text-sm font-semibold tracking-wide text-on-surface transition-colors hover:text-primary" href="{{ route('home') }}">
                <span class="flex size-8 items-center justify-center rounded-lg bg-primary-container font-display text-sm font-bold text-on-primary">N</span>
                <span>{{ config('app.name', 'NeuralLog') }}</span>
            </a>

            <nav class="hidden items-center gap-6 sm:flex" aria-label="Primary navigation">
                <a class="font-mono text-xs uppercase tracking-[0.16em] text-primary" href="{{ route('home') }}">Home</a>
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:text-primary" type="submit">Sign Out</button>
                    </form>
                @else
                    <a class="font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:text-primary" href="{{ route('login') }}">Sign In</a>
                    <a class="font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:text-primary" href="{{ route('register') }}">Register</a>
                @endauth
            </nav>

            <button
                class="rounded-md border border-outline-variant px-3 py-2 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:border-primary hover:text-primary sm:hidden"
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
            class="border-t border-outline-variant/50 px-4 py-3 sm:hidden"
            x-cloak
            x-show="mobileMenuOpen"
            x-transition.origin.top
            aria-label="Mobile navigation"
        >
            <a
                class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-primary transition-colors hover:bg-surface-high"
                href="{{ route('home') }}"
                @click="mobileMenuOpen = false"
            >
                Home
            </a>
            @auth
                <form class="px-3 py-3" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:text-primary" type="submit">Sign Out</button>
                </form>
            @else
                <a class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" href="{{ route('login') }}" @click="mobileMenuOpen = false">Sign In</a>
                <a class="block rounded-md px-3 py-3 font-mono text-xs uppercase tracking-[0.16em] text-on-surface-variant transition-colors hover:bg-surface-high hover:text-primary" href="{{ route('register') }}" @click="mobileMenuOpen = false">Register</a>
            @endauth
        </nav>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        @yield('content')
    </main>
</body>
</html>
