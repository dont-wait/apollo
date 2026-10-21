<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class PasswordResetOtpService
{
    public const PURPOSE_PASSWORD_CHANGE = 'password_change';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    /**
     * Issue an OTP or return the current flow during its resend cooldown.
     *
     * @return array{flow_id: string, user_id: int, email: string, sent: bool}|null
     */
    public function send(string $email, string $purpose = self::PURPOSE_PASSWORD_RESET): ?array
    {
        if (! in_array($purpose, [self::PURPOSE_PASSWORD_RESET, self::PURPOSE_PASSWORD_CHANGE], true)) {
            throw new InvalidArgumentException('Unsupported password OTP purpose.');
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->first();

        if (! $user || ! $user->isActive()) {
            return null;
        }

        $now = now();
        $otp = $this->mockOtpEnabled()
            ? (string) config('auth.mock_otp')
            : (string) random_int(100000, 999999);

        try {
            $result = DB::transaction(function () use ($user, $purpose, $otp, $now): array {
                $record = DB::table('password_reset_otps')
                    ->where('user_id', $user->id)
                    ->where('purpose', $purpose)
                    ->lockForUpdate()
                    ->first();

                if (
                    $record
                    && $record->flow_id
                    && Carbon::parse($record->last_sent_at)->greaterThan($now->copy()->subSeconds(60))
                ) {
                    return [
                        'flow_id' => $record->flow_id,
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'sent' => false,
                    ];
                }

                $flowId = (string) Str::uuid();

                DB::table('password_reset_otps')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'purpose' => $purpose,
                    ],
                    [
                        'email' => $user->email,
                        'flow_id' => $flowId,
                        'otp_hash' => Hash::make($otp),
                        'attempts' => 0,
                        'expires_at' => $now->copy()->addMinutes(5),
                        'last_sent_at' => $now,
                        'verified_at' => null,
                        'delivered_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                return [
                    'flow_id' => $flowId,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'sent' => true,
                ];
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            $record = DB::table('password_reset_otps')
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->first();

            if (
                ! $record
                || ! $record->flow_id
                || ! Carbon::parse($record->last_sent_at)->greaterThan($now->copy()->subSeconds(60))
            ) {
                throw $exception;
            }

            return [
                'flow_id' => $record->flow_id,
                'user_id' => $user->id,
                'email' => $user->email,
                'sent' => false,
            ];
        }

        if (! $result['sent']) {
            return $result;
        }

        try {
            $user->notify(new PasswordResetOtp($otp, $result['flow_id'], $purpose));
        } catch (Throwable $exception) {
            DB::table('password_reset_otps')->where('flow_id', $result['flow_id'])->delete();
            report($exception);

            return null;
        }

        return $result;
    }

    public function verify(string $flowId, int $userId, string $otp, string $purpose): bool
    {
        return DB::transaction(function () use ($flowId, $userId, $otp, $purpose): bool {
            $record = DB::table('password_reset_otps')
                ->where('flow_id', $flowId)
                ->where('user_id', $userId)
                ->where('purpose', $purpose)
                ->lockForUpdate()
                ->first();

            if (! $record || ! $this->matchesCurrentUser($record, $userId) || $record->attempts >= 5 || now()->greaterThan($record->expires_at)) {
                return false;
            }

            if (! Hash::check($otp, $record->otp_hash)) {
                DB::table('password_reset_otps')->where('flow_id', $flowId)->increment('attempts');

                return false;
            }

            DB::table('password_reset_otps')->where('flow_id', $flowId)->update([
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    public function reset(string $flowId, int $userId, string $password): bool
    {
        return $this->completePasswordChange(
            $flowId,
            $userId,
            $password,
            self::PURPOSE_PASSWORD_RESET,
            invalidateSessions: true,
        );
    }

    public function changePassword(string $flowId, int $userId, string $password): bool
    {
        return $this->completePasswordChange(
            $flowId,
            $userId,
            $password,
            self::PURPOSE_PASSWORD_CHANGE,
            invalidateSessions: false,
        );
    }

    private function completePasswordChange(
        string $flowId,
        int $userId,
        string $password,
        string $purpose,
        bool $invalidateSessions,
    ): bool {
        return DB::transaction(function () use ($flowId, $userId, $password, $purpose, $invalidateSessions): bool {
            $record = DB::table('password_reset_otps')
                ->where('flow_id', $flowId)
                ->where('user_id', $userId)
                ->where('purpose', $purpose)
                ->whereNotNull('verified_at')
                ->lockForUpdate()
                ->first();

            if (! $record || now()->greaterThan($record->expires_at) || ! $this->matchesCurrentUser($record, $userId)) {
                return false;
            }

            $user = User::query()->lockForUpdate()->find($userId);

            if (! $user || ! $user->isActive()) {
                DB::table('password_reset_otps')->where('flow_id', $flowId)->delete();

                return false;
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            if ($invalidateSessions && DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            if ($invalidateSessions) {
                event(new PasswordReset($user));
            }

            DB::table('password_reset_otps')->where('flow_id', $flowId)->delete();

            return true;
        });
    }

    private function matchesCurrentUser(object $record, int $userId): bool
    {
        $user = User::query()->find($userId);

        if (! $user || ! $user->isActive()) {
            return false;
        }

        return strtolower($user->email) === strtolower($record->email);
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            || str_contains(strtolower($exception->getMessage()), 'duplicate');
    }

    private function mockOtpEnabled(): bool
    {
        return (bool) config('auth.enable_mock_otp') && app()->environment(['local', 'testing']);
    }
}
