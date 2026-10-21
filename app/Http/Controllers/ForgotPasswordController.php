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
        $email = strtolower(trim($request->validated('email')));
        $result = $otpService->send($email, PasswordResetOtpService::PURPOSE_PASSWORD_RESET);

        $request->session()->put('password_reset.email', $email);

        if ($result) {
            $request->session()->put([
                'password_reset.user_id' => $result['user_id'],
                'password_reset.flow_id' => $result['flow_id'],
            ]);
        } else {
            $request->session()->forget(['password_reset.user_id', 'password_reset.flow_id']);
        }

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

        $result = $otpService->send($email, PasswordResetOtpService::PURPOSE_PASSWORD_RESET);

        if ($result) {
            $request->session()->put([
                'password_reset.user_id' => $result['user_id'],
                'password_reset.flow_id' => $result['flow_id'],
            ]);
        } else {
            $request->session()->forget(['password_reset.user_id', 'password_reset.flow_id']);
        }

        return redirect()->route('password.otp')->with(
            'status',
            'If an account exists for this email, a verification code is available. Please use the latest code.',
        );
    }
}
