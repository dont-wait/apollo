<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
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
}
