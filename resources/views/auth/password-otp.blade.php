@extends('layouts.auth')

@section('title', $pageTitle ?? 'Verify OTP · Apollo Blog')

@section('content')
    <div class="flex flex-col gap-6 rounded-xl bg-surface-low p-6 shadow-2xl shadow-black/20 sm:p-8">
        @include('auth.partials.brand', [
            'heading' => $heading ?? 'Check your inbox',
            'description' => $description ?? 'Enter the six-digit code we sent to '.session('password_reset.email').'.',
        ])

        @if (session('status'))
            <div class="rounded-lg border border-primary/30 bg-primary/10 px-3 py-3 text-sm text-on-surface" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-error/40 bg-error-container/30 px-3 py-3 text-sm text-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form
            class="flex flex-col gap-5"
            method="POST"
            action="{{ route($otpRoute ?? 'password.otp.verify') }}"
            x-data="{
                digits: ['', '', '', '', '', ''],
                submitting: false,
                fields() { return this.$root.querySelectorAll('[data-otp-input]'); },
                update(value, index) {
                    const sanitized = value.replace(/[^0-9]/g, '');
                    if (sanitized.length > 1) {
                        sanitized.slice(0, 6).split('').forEach((digit, digitIndex) => this.digits[digitIndex] = digit);
                        this.fields().forEach((field, fieldIndex) => field.value = this.digits[fieldIndex]);
                        this.submitIfComplete();

                        return;
                    }

                    this.digits[index] = sanitized;
                    const fields = this.fields();
                    if (this.digits[index] && index < 5) fields[index + 1].focus();
                    this.submitIfComplete();
                },
                backspace(event, index) {
                    if (event.key === 'Backspace' && !this.digits[index] && index > 0) this.fields()[index - 1].focus();
                },
                paste(event) {
                    event.preventDefault();
                    const pasted = event.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6).split('');
                    pasted.forEach((digit, index) => this.digits[index] = digit);
                    const fields = this.fields();
                    pasted.forEach((digit, index) => fields[index].value = digit);
                    fields[Math.min(pasted.length, 6) - 1]?.focus();
                    this.submitIfComplete();
                },
                submitIfComplete() {
                    if (this.digits.join('').length !== 6 || this.submitting) return;
                    this.$refs.otp.value = this.digits.join('');
                    this.submitting = true;
                    this.$root.submit();
                }
            }"
        >
            @csrf
            <input type="hidden" name="otp" x-ref="otp">
            <div class="flex justify-center gap-2 sm:gap-3" role="group" aria-label="Six-digit verification code">
                @foreach (range(0, 5) as $index)
                    <input
                        class="size-12 rounded-lg border border-outline-variant/60 bg-surface text-center font-mono text-xl font-semibold text-on-surface outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary sm:size-14"
                        type="text"
                        inputmode="numeric"
                        maxlength="1"
                        autocomplete="one-time-code"
                        data-otp-input
                        aria-label="Digit {{ $index + 1 }}"
                        @input="update($event.target.value, {{ $index }})"
                        @keydown="backspace($event, {{ $index }})"
                        @paste="paste($event)"
                        @if ($index === 0) autofocus @endif
                    >
                @endforeach
            </div>
            @error('otp') <p class="text-center text-xs text-error">{{ $message }}</p> @enderror
            <p class="text-center font-mono text-[11px] uppercase tracking-[0.12em] text-on-surface-variant">The code expires in 5 minutes</p>
        </form>

        <div class="flex items-center justify-between gap-3">
            <a class="font-mono text-xs text-on-surface-variant transition-colors hover:text-primary" href="{{ route($backRoute ?? 'password.request') }}">{{ $backLabel ?? 'Use a different email' }}</a>
            <span class="font-mono text-[10px] uppercase tracking-wider text-on-surface-variant/60">Auto verify</span>
        </div>
    </div>
@endsection
