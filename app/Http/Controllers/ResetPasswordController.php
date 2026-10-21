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
        if (! session('password_reset.email') || ! session('password_reset.user_id') || ! session('password_reset.flow_id')) {
            return redirect()->route('password.request');
        }

        return view('auth.password-otp', [
            'resendRoute' => 'password.otp.resend',
            'backRoute' => 'password.request',
            'backLabel' => 'Use a different email',
        ]);
    }

    public function verifyOtp(VerifyPasswordResetOtpRequest $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $flowId = (string) $request->session()->get('password_reset.flow_id');
        $userId = (int) $request->session()->get('password_reset.user_id');

        if (
            ! $flowId
            || ! $userId
            || ! $otpService->verify(
                $flowId,
                $userId,
                $request->validated('otp'),
                PasswordResetOtpService::PURPOSE_PASSWORD_RESET,
            )
        ) {
            return back()->withInput()->withErrors([
                'otp' => 'The code is invalid, expired, or has reached its attempt limit.',
            ]);
        }

        $request->session()->put('password_reset.verified', true);

        return redirect()->route('password.reset');
    }

    public function show(): View|RedirectResponse
    {
        if (
            ! session('password_reset.email')
            || ! session('password_reset.user_id')
            || ! session('password_reset.flow_id')
            || ! session('password_reset.verified')
        ) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function reset(ResetPasswordRequest $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $flowId = (string) $request->session()->get('password_reset.flow_id');
        $userId = (int) $request->session()->get('password_reset.user_id');

        if (! $request->session()->get('password_reset.verified')) {
            return redirect()->route('password.request');
        }

        if (
            ! $flowId
            || ! $userId
            || ! $otpService->reset($flowId, $userId, $request->validated('password'))
        ) {
            $request->session()->forget('password_reset');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'Your reset session has expired. Please request a new code.']);
        }

        $request->session()->forget('password_reset');

        return redirect()->route('login')->with('status', 'Your password has been reset. Please sign in.');
    }
}
