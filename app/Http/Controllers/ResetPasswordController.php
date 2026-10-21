<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyPasswordResetOtpRequest;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function showOtp(): View|RedirectResponse
    {
        if (! session('password_reset.email')) {
            return redirect()->route('password.request');
        }

        return view('auth.password-otp');
    }

    public function verifyOtp(VerifyPasswordResetOtpRequest $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $email = (string) $request->session()->get('password_reset.email');

        if (! $otpService->verify($email, $request->validated('otp'))) {
            return back()->withInput()->withErrors([
                'otp' => 'The code is invalid, expired, or has reached its attempt limit.',
            ]);
        }

        $request->session()->put('password_reset.verified', true);

        return redirect()->route('password.reset');
    }

    public function show(): View|RedirectResponse
    {
        if (! session('password_reset.email') || ! session('password_reset.verified')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function reset(ResetPasswordRequest $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $email = (string) $request->session()->get('password_reset.email');

        if (! $request->session()->get('password_reset.verified')) {
            return redirect()->route('password.request');
        }

        if (! $otpService->reset($email, $request->validated('password'))) {
            $request->session()->forget('password_reset');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'Your reset session has expired. Please request a new code.']);
        }

        $request->session()->forget('password_reset');

        return redirect()->route('login')->with('status', 'Your password has been reset. Please sign in.');
    }
}
