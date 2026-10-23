@extends('layouts.auth')

@section('title', 'Forgot Password · Apollo Blog')

@section('content')
    <div class="flex flex-col gap-6 rounded-xl bg-surface-low p-6 shadow-2xl shadow-black/20 sm:p-8">
        @include('auth.partials.brand', [
            'heading' => 'Reset your password',
            'description' => 'We will send a one-time code to the email address linked to your account.',
        ])

        @if ($errors->any())
            <div class="rounded-lg border border-error/40 bg-error-container/30 px-3 py-3 text-sm text-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form class="flex flex-col gap-4" method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-xs text-on-surface-variant" for="email">Account email</label>
                <input class="h-11 rounded-lg border border-outline-variant/60 bg-surface px-3 text-sm text-on-surface outline-none transition-colors placeholder:text-on-surface-variant/40 focus:border-primary focus:ring-1 focus:ring-primary" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@organization.com" required autofocus>
                @error('email') <p class="text-xs text-error">{{ $message }}</p> @enderror
            </div>
            <button class="flex h-11 items-center justify-center rounded-lg bg-primary font-display text-sm font-semibold text-on-primary shadow-md transition-all hover:bg-surface-tint" type="submit">Send reset code <span class="ml-2" aria-hidden="true">→</span></button>
        </form>

        <a class="text-center font-mono text-xs text-on-surface-variant transition-colors hover:text-primary" href="{{ route('login') }}">← Back to sign in</a>
    </div>
@endsection
