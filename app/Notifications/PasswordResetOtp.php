<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtp extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $otp)
    {
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
