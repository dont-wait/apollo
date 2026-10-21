<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetOtpService
{
    public function send(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->isActive()) {
            return;
        }

        $now = now();
        $otp = $this->mockOtpEnabled()
            ? (string) config('auth.mock_otp')
            : (string) random_int(100000, 999999);

        $shouldSend = DB::transaction(function () use ($email, $otp, $now): bool {
            $record = DB::table('password_reset_otps')->where('email', $email)->lockForUpdate()->first();

            if ($record && Carbon::parse($record->last_sent_at)->greaterThan($now->copy()->subSeconds(60))) {
                return false;
            }

            DB::table('password_reset_otps')->updateOrInsert(
                ['email' => $email],
                [
                    'otp_hash' => Hash::make($otp),
                    'attempts' => 0,
                    'expires_at' => $now->copy()->addMinutes(5),
                    'last_sent_at' => $now,
                    'verified_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            return true;
        });

        if ($shouldSend) {
            $user->notify(new PasswordResetOtp($otp));
        }
    }

    public function verify(string $email, string $otp): bool
    {
        return DB::transaction(function () use ($email, $otp): bool {
            $record = DB::table('password_reset_otps')->where('email', $email)->lockForUpdate()->first();

            if (! $record || $record->attempts >= 5 || now()->greaterThan($record->expires_at)) {
                return false;
            }

            if (! Hash::check($otp, $record->otp_hash)) {
                DB::table('password_reset_otps')->where('email', $email)->increment('attempts');

                return false;
            }

            DB::table('password_reset_otps')->where('email', $email)->update([
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    public function reset(string $email, string $password): bool
    {
        return DB::transaction(function () use ($email, $password): bool {
            $record = DB::table('password_reset_otps')->where('email', $email)->lockForUpdate()->first();

            if (! $record || ! $record->verified_at || now()->greaterThan($record->expires_at)) {
                return false;
            }

            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if (! $user || ! $user->isActive()) {
                DB::table('password_reset_otps')->where('email', $email)->delete();

                return false;
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));

            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            DB::table('password_reset_otps')->where('email', $email)->delete();

            return true;
        });
    }

    public function changePassword(string $email, string $password): bool
    {
        return DB::transaction(function () use ($email, $password): bool {
            $record = DB::table('password_reset_otps')->where('email', $email)->lockForUpdate()->first();

            if (! $record || ! $record->verified_at || now()->greaterThan($record->expires_at)) {
                return false;
            }

            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if (! $user || ! $user->isActive()) {
                DB::table('password_reset_otps')->where('email', $email)->delete();

                return false;
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('password_reset_otps')->where('email', $email)->delete();

            return true;
        });
    }

    private function mockOtpEnabled(): bool
    {
        return (bool) config('auth.enable_mock_otp') && app()->environment(['local', 'testing']);
    }
}
