@extends('layouts.auth')

@section('title', 'Create Account · NeuralLog')

@section('content')
    <div class="flex flex-col gap-6 rounded-xl bg-surface-low p-6 shadow-2xl shadow-black/20 sm:p-8">
        @include('auth.partials.brand', [
            'heading' => 'Join NeuralLog Research',
            'description' => 'Create your developer profile to publish analyses, follow papers, and customize topics.',
        ])

        <nav class="flex w-full items-center gap-1 rounded-lg bg-surface p-1 shadow-inner" aria-label="Authentication">
            <a class="flex-1 rounded-lg px-3 py-2 text-center font-mono text-xs text-on-surface-variant transition-colors hover:text-on-surface" href="{{ route('login') }}">Sign In</a>
            <a class="flex-1 rounded-lg bg-surface-high px-3 py-2 text-center font-mono text-xs font-semibold text-primary shadow-sm" href="{{ route('register') }}" aria-current="page">Create Account</a>
        </nav>

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
                        <span x-text="showPassword ? 'Hide' : 'Show'"></span>
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
                        <span x-text="showPassword ? 'Hide' : 'Show'"></span>
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
