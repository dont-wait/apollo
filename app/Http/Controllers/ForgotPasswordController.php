<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.forgot-password');
    }

    public function send(ForgotPasswordRequest $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $email = strtolower($request->validated('email'));
        $otpService->send($email);

        $request->session()->put('password_reset.email', $email);
        $request->session()->forget('password_reset.verified');

        return redirect()->route('password.otp')
            ->with('status', 'If an active account exists for that email, a reset code has been sent.');
    }

    public function resend(Request $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $email = (string) $request->session()->get('password_reset.email');

        if (! $email) {
            return redirect()->route('password.request');
        }

        $sent = $otpService->send($email);

        return redirect()->route('password.otp')->with('status', $sent
            ? 'A new verification code has been sent to your email.'
            : 'Please wait at least 60 seconds before requesting another code.');
    }
}
