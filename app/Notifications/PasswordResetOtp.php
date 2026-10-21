<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

class PasswordResetOtp extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $otp,
        public readonly string $flowId,
        public readonly string $purpose,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $record = DB::table('password_reset_otps')
            ->where('flow_id', $this->flowId)
            ->where('user_id', $notifiable->getKey())
            ->where('purpose', $this->purpose)
            ->first();

        if (! $record || $record->verified_at || strtolower($record->email) !== strtolower($notifiable->email)) {
            return false;
        }

        $now = now();

        if ($record->delivered_at && $now->greaterThan($record->expires_at)) {
            return false;
        }

        return true;
    }

    public function afterSending(object $notifiable, string $channel, mixed $response): void
    {
        $now = now();

        DB::table('password_reset_otps')
            ->where('flow_id', $this->flowId)
            ->where('user_id', $notifiable->getKey())
            ->where('purpose', $this->purpose)
            ->whereNull('verified_at')
            ->update([
                'delivered_at' => $now,
                'expires_at' => $now->copy()->addMinutes(5),
                'updated_at' => $now,
            ]);
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('password_reset_otps')
            ->where('flow_id', $this->flowId)
            ->where('purpose', $this->purpose)
            ->delete();
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Apollo Blog — Password reset code')
            ->view(
                ['emails.auth.password-reset-otp', 'emails.auth.password-reset-otp-text'],
                ['otp' => $this->otp, 'expiresInMinutes' => 5],
            );
    }
}
