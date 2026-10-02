@extends('layouts.auth')

@section('title', 'Create Account · Apollo Blog')

@section('content')
    <div class="flex flex-col gap-6 rounded-xl bg-surface-low p-6 shadow-2xl shadow-black/20 sm:p-8">
        @include('auth.partials.brand', [
            'heading' => 'Join Apollo Blog',
            'description' => 'Create your developer profile to publish analyses, follow papers, and customize topics.',
        ])

        <nav class="flex w-full items-center gap-1 rounded-lg bg-surface p-1 shadow-inner" aria-label="Authentication">
            <a class="flex-1 rounded-lg px-3 py-2 text-center font-mono text-xs text-on-surface-variant transition-colors hover:text-on-surface" href="{{ route('login') }}">Sign In</a>
            <a class="flex-1 rounded-lg bg-surface-high px-3 py-2 text-center font-mono text-xs font-semibold text-primary shadow-sm" href="{{ route('register') }}" aria-current="page">Create Account</a>
        </nav>

        <div class="flex flex-col gap-2">
            <button class="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-surface-high px-4 text-sm font-medium text-on-surface transition-colors hover:bg-surface-highest" type="button" disabled title="GitHub sign-in coming soon">
                <svg class="size-4 text-primary" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 4.42 2.87 8.18 6.84 9.5.5.09.68-.22.68-.48 0-.24-.01-.87-.01-1.7-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.61.07-.61 1 .07 1.53 1.03 1.53 1.03.89 1.53 2.34 1.09 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.56-1.11-4.56-4.95 0-1.09.39-1.99 1.03-2.69-.1-.25-.45-1.27.1-2.65 0 0 .84-.27 2.75 1.03A9.56 9.56 0 0 1 12 6.84a9.6 9.6 0 0 1 2.5.34c1.91-1.3 2.75-1.03 2.75-1.03.55 1.38.2 2.4.1 2.65.64.7 1.03 1.6 1.03 2.69 0 3.85-2.34 4.7-4.57 4.94.36.31.68.92.68 1.86 0 1.34-.01 2.42-.01 2.75 0 .27.18.58.69.48A10.02 10.02 0 0 0 22 12C22 6.48 17.52 2 12 2Z"></path>
                </svg>
                <span>Continue with GitHub</span>
            </button>
            <button class="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-surface-high px-4 text-sm font-medium text-on-surface transition-colors hover:bg-surface-highest" type="button" disabled title="Google sign-in coming soon">
                <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.75 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6a5.64 5.64 0 0 1-2.4 3.68v3.05h3.88c2.27-2.09 3.67-5.17 3.67-9.17Z"></path>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24Z"></path>
                    <path fill="#FBBC05" d="M5.28 14.27A7.3 7.3 0 0 1 4.9 12c0-.78.13-1.55.38-2.27V6.58H1.25A12 12 0 0 0 0 12c0 1.93.45 3.82 1.25 5.42l4.03-3.15Z"></path>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98Z"></path>
                </svg>
                <span>Continue with Google</span>
            </button>
        </div>

        <div class="relative flex items-center justify-center">
            <div class="h-px w-full bg-surface-highest"></div>
            <span class="absolute bg-surface-low px-2 font-mono text-[10px] uppercase tracking-wider text-on-surface-variant">Initialize profile</span>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-error/40 bg-error-container/30 px-3 py-3 text-sm text-error" role="alert">
                <p class="font-semibold">Please review the highlighted fields.</p>
                <ul class="mt-1 list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="flex flex-col gap-4" method="POST" action="{{ route('register.store') }}">
            @csrf

            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-xs text-on-surface-variant" for="name">Full Name / Engineering Alias</label>
                <input class="h-11 rounded-lg border border-outline-variant/60 bg-surface px-3 text-sm text-on-surface outline-none transition-colors placeholder:text-on-surface-variant/40 focus:border-primary focus:ring-1 focus:ring-primary" id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Dr. Ada Lovelace" autocomplete="name" required autofocus>
                @error('name')
                    <p class="text-xs text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-xs text-on-surface-variant" for="email">Work / Personal Email</label>
                <input class="h-11 rounded-lg border border-outline-variant/60 bg-surface px-3 text-sm text-on-surface outline-none transition-colors placeholder:text-on-surface-variant/40 focus:border-primary focus:ring-1 focus:ring-primary" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="name@organization.com" autocomplete="email" required>
                @error('email')
                    <p class="text-xs text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5" x-data="{ showPassword: false }">
                <label class="font-mono text-xs text-on-surface-variant" for="password">Password</label>
                <div class="relative">
                    <input class="h-11 w-full rounded-lg border border-outline-variant/60 bg-surface px-3 pr-11 text-sm text-on-surface outline-none transition-colors placeholder:text-on-surface-variant/40 focus:border-primary focus:ring-1 focus:ring-primary" id="password" name="password" :type="showPassword ? 'text' : 'password'" placeholder="At least 8 characters" autocomplete="new-password" required>
                    <button class="absolute inset-y-0 right-2 flex items-center rounded px-2 text-xs text-on-surface-variant transition-colors hover:text-on-surface" type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                        <svg class="size-5" x-show="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"></path>
                            <circle cx="12" cy="12" r="2.5"></circle>
                        </svg>
                        <svg class="size-5" x-cloak x-show="showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m3 3 18 18"></path>
                            <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a17.4 17.4 0 0 1-3.2 3.7M6.2 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.8-.4 4-.9"></path>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="text-xs text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5" x-data="{ showPassword: false }">
                <label class="font-mono text-xs text-on-surface-variant" for="password_confirmation">Confirm Password</label>
                <div class="relative">
                    <input class="h-11 w-full rounded-lg border border-outline-variant/60 bg-surface px-3 pr-11 text-sm text-on-surface outline-none transition-colors placeholder:text-on-surface-variant/40 focus:border-primary focus:ring-1 focus:ring-primary" id="password_confirmation" name="password_confirmation" :type="showPassword ? 'text' : 'password'" placeholder="Repeat your password" autocomplete="new-password" required>
                    <button class="absolute inset-y-0 right-2 flex items-center rounded px-2 text-xs text-on-surface-variant transition-colors hover:text-on-surface" type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                        <svg class="size-5" x-show="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"></path>
                            <circle cx="12" cy="12" r="2.5"></circle>
                        </svg>
                        <svg class="size-5" x-cloak x-show="showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m3 3 18 18"></path>
                            <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a17.4 17.4 0 0 1-3.2 3.7M6.2 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.5 0 2.8-.4 4-.9"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <button class="mt-1 flex h-11 items-center justify-center rounded-lg bg-primary font-display text-sm font-semibold text-on-primary shadow-md transition-all hover:bg-surface-tint hover:shadow-lg active:scale-[0.99]" type="submit">
                Initialize Account
                <span class="ml-2" aria-hidden="true">→</span>
            </button>
        </form>

        @include('auth.partials.footer')
    </div>
@endsection
