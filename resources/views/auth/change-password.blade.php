@extends('layouts.auth')

@section('title', 'Change Password · Apollo Blog')

@section('content')
    <div class="flex flex-col gap-6 rounded-xl bg-surface-low p-6 shadow-2xl shadow-black/20 sm:p-8">
        @include('auth.partials.brand', [
            'heading' => 'Change your password',
            'description' => 'Confirm your current password before choosing a new one.',
        ])

        @if (session('status'))
            <div class="rounded-lg border border-primary/30 bg-primary/10 px-3 py-3 text-sm text-on-surface" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-error/40 bg-error-container/30 px-3 py-3 text-sm text-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form class="flex flex-col gap-4" method="POST" action="{{ route('password.change.update') }}" x-data="{ showPassword: false }">
            @csrf
            @method('PUT')
            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-xs text-on-surface-variant" for="current_password">Current password</label>
                <input class="h-11 rounded-lg border border-outline-variant/60 bg-surface px-3 text-sm text-on-surface outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary" id="current_password" name="current_password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required autofocus>
                @error('current_password') <p class="text-xs text-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-xs text-on-surface-variant" for="password">New password</label>
                <input class="h-11 rounded-lg border border-outline-variant/60 bg-surface px-3 text-sm text-on-surface outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary" id="password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" minlength="8" required>
                @error('password') <p class="text-xs text-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-xs text-on-surface-variant" for="password_confirmation">Confirm new password</label>
                <input class="h-11 rounded-lg border border-outline-variant/60 bg-surface px-3 text-sm text-on-surface outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary" id="password_confirmation" name="password_confirmation" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" minlength="8" required>
            </div>
            <label class="flex cursor-pointer items-center gap-2 text-xs text-on-surface-variant">
                <input class="size-4 rounded border-outline-variant bg-surface text-primary focus:ring-primary" type="checkbox" @change="showPassword = $event.target.checked">
                <span>Show passwords</span>
            </label>
            <button class="flex h-11 items-center justify-center rounded-lg bg-primary font-display text-sm font-semibold text-on-primary shadow-md transition-all hover:bg-surface-tint" type="submit">Update password <span class="ml-2" aria-hidden="true">→</span></button>
        </form>
        <a class="text-center font-mono text-xs text-on-surface-variant transition-colors hover:text-primary" href="{{ route('home') }}">← Back to Apollo Blog</a>
    </div>
@endsection
