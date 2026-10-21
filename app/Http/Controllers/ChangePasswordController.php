<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\VerifyPasswordResetOtpRequest;
use App\Services\PasswordResetOtpService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $user = $request->user();
        $password = $request->validated('password');

        $result = $otpService->send($user->email, PasswordResetOtpService::PURPOSE_PASSWORD_CHANGE);
        $currentFlowId = (string) $request->session()->get('password_change.flow_id');

        if (! $result || (! $result['sent'] && $currentFlowId !== $result['flow_id'])) {
            if (! $result) {
                $request->session()->forget('password_change');

                return back()->withErrors([
                    'password' => 'We could not send a verification code. Please try again.',
                ]);
            }

            return back()->withErrors([
                'password' => 'A verification code is already pending. Please use it or wait before trying again.',
            ]);
        }

        $request->session()->put([
            'password_change.email' => $user->email,
            'password_change.user_id' => $user->id,
            'password_change.flow_id' => $result['flow_id'],
        ]);

        if ($result['sent']) {
            $request->session()->put('password_change.password', Crypt::encryptString($password));
        }

        return redirect()->route('password.change.otp')
            ->with(
                'status',
                $result['sent']
                    ? 'A verification code has been sent to your email.'
                    : 'A verification code is already pending. Please use the latest code.',
            );
    }

    public function showOtp(): View|RedirectResponse
    {
        if (! session('password_change.email') || ! session('password_change.user_id') || ! session('password_change.flow_id')) {
            return redirect()->route('password.change');
        }

        return view('auth.password-otp', [
            'pageTitle' => 'Verify Password Change · Apollo Blog',
            'heading' => 'Confirm password change',
            'description' => 'Enter the six-digit code sent to '.session('password_change.email').'.',
            'otpRoute' => 'password.change.otp.verify',
            'resendRoute' => 'password.change.otp.resend',
            'backRoute' => 'password.change',
            'backLabel' => 'Back to change password',
        ]);
    }

    public function verifyOtp(VerifyPasswordResetOtpRequest $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $flowId = (string) $request->session()->get('password_change.flow_id');
        $userId = (int) $request->session()->get('password_change.user_id');

        if (! $flowId || ! $userId || $userId !== $request->user()->id) {
            return redirect()->route('password.change');
        }

        if (! $otpService->verify(
            $flowId,
            $userId,
            $request->validated('otp'),
            PasswordResetOtpService::PURPOSE_PASSWORD_CHANGE,
        )) {
            return back()->withInput()->withErrors([
                'otp' => 'The code is invalid, expired, or has reached its attempt limit.',
            ]);
        }

        try {
            $password = Crypt::decryptString((string) $request->session()->get('password_change.password'));
        } catch (DecryptException) {
            $request->session()->forget('password_change');

            return redirect()->route('password.change')
                ->withErrors(['password' => 'Your change-password session has expired. Please try again.']);
        }

        if (! $otpService->changePassword($flowId, $userId, $password)) {
            $request->session()->forget('password_change');

            return redirect()->route('password.change')
                ->withErrors(['email' => 'Your change-password session has expired. Please try again.']);
        }

        $request->user()->refresh();
        Auth::logoutOtherDevices($password);
        $request->session()->forget('password_change');

        return redirect()->route('password.change')->with('status', 'Your password has been changed.');
    }

    public function resendOtp(Request $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        if (
            $request->session()->get('password_change.user_id') !== $user->id
            || $request->session()->get('password_change.email') !== $user->email
        ) {
            return redirect()->route('password.change');
        }

        $result = $otpService->send($user->email, PasswordResetOtpService::PURPOSE_PASSWORD_CHANGE);

        if ($result) {
            $request->session()->put('password_change.flow_id', $result['flow_id']);
        } else {
            $request->session()->forget('password_change');

            return redirect()->route('password.change')
                ->withErrors(['password' => 'We could not send a verification code. Please try again.']);
        }

        return redirect()->route('password.change.otp')->with(
            'status',
            $result && $result['sent']
                ? 'A new verification code has been sent to your email.'
                : 'Please wait at least 60 seconds before requesting another code.',
        );
    }
}
