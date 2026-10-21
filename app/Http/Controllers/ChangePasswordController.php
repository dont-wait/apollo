<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\VerifyPasswordResetOtpRequest;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
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

        $otpService->send($user->email);
        $request->session()->put([
            'password_change.email' => $user->email,
            'password_change.user_id' => $user->id,
            'password_change.password' => Crypt::encryptString($password),
        ]);

        return redirect()->route('password.change.otp')
            ->with('status', 'A verification code has been sent to your email.');
    }

    public function showOtp(): View|RedirectResponse
    {
        if (! session('password_change.email')) {
            return redirect()->route('password.change');
        }

        return view('auth.password-otp', [
            'pageTitle' => 'Verify Password Change · Apollo Blog',
            'heading' => 'Confirm password change',
            'description' => 'Enter the six-digit code sent to '.session('password_change.email').'.',
            'otpRoute' => 'password.change.otp.verify',
            'backRoute' => 'password.change',
            'backLabel' => 'Back to change password',
        ]);
    }

    public function verifyOtp(VerifyPasswordResetOtpRequest $request, PasswordResetOtpService $otpService): RedirectResponse
    {
        $email = (string) $request->session()->get('password_change.email');

        if (! $email || $request->session()->get('password_change.user_id') !== $request->user()->id) {
            return redirect()->route('password.change');
        }

        if (! $otpService->verify($email, $request->validated('otp'))) {
            return back()->withInput()->withErrors([
                'otp' => 'The code is invalid, expired, or has reached its attempt limit.',
            ]);
        }

        $encryptedPassword = (string) $request->session()->get('password_change.password');
        $password = Crypt::decryptString($encryptedPassword);

        if (! $otpService->changePassword($email, $password)) {
            $request->session()->forget('password_change');

            return redirect()->route('password.change')
                ->withErrors(['email' => 'Your change-password session has expired. Please try again.']);
        }

        $request->user()->refresh();
        Auth::logoutOtherDevices($password);
        $request->session()->forget('password_change');

        return redirect()->route('password.change')->with('status', 'Your password has been changed.');
    }
}
