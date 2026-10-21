<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_mock_otp_resets_the_password_and_is_sent_by_email(): void
    {
        config(['auth.enable_mock_otp' => true]);
        Notification::fake();
        $oldPassword = 'old-password-1';
        $user = User::factory()->create(['password' => $oldPassword]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.otp'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_otps', [
            'email' => $user->email,
            'attempts' => 0,
        ]);
        $otpHash = (string) DB::table('password_reset_otps')->where('email', $user->email)->value('otp_hash');
        $this->assertTrue(Hash::check('666666', $otpHash));

        $this->post(route('password.otp.verify'), ['otp' => '666666'])
            ->assertRedirect(route('password.reset'));

        $newPassword = 'new-password-123';
        $this->post(route('password.update'), [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);
        Notification::assertSentTo($user, PasswordResetOtp::class, fn (PasswordResetOtp $notification): bool => $notification->otp === '666666');
    }

    public function test_real_otp_is_sent_with_the_email_template_when_mocking_is_disabled(): void
    {
        config(['auth.enable_mock_otp' => false]);
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.otp'));

        Notification::assertSentTo($user, PasswordResetOtp::class, function (PasswordResetOtp $notification): bool {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $notification->otp);
            $mail = $notification->toMail(new User);
            $this->assertSame('Apollo Blog — Password reset code', $mail->subject);
            $this->assertSame([
                'emails.auth.password-reset-otp',
                'emails.auth.password-reset-otp-text',
            ], $mail->view);
            $this->assertSame($notification->otp, $mail->viewData['otp']);

            $html = view('emails.auth.password-reset-otp', [
                'otp' => $notification->otp,
                'expiresInMinutes' => 5,
            ])->render();

            $this->assertStringContainsString($notification->otp, $html);
            $this->assertStringContainsString('never share this code', $html);
            $this->assertStringContainsString('expires in', $html);

            return true;
        });
    }

    public function test_forgot_password_response_does_not_reveal_unknown_email(): void
    {
        config(['auth.enable_mock_otp' => true]);
        $response = $this->post(route('password.email'), ['email' => 'missing@example.test']);

        $response->assertRedirect(route('password.otp'))
            ->assertSessionHas('status', 'If an active account exists for that email, a reset code has been sent.');
        $this->assertDatabaseCount('password_reset_otps', 0);
    }

    public function test_invalid_codes_expire_and_lock_after_five_attempts(): void
    {
        config(['auth.enable_mock_otp' => true]);
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.otp'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('password.otp'))->post(route('password.otp.verify'), ['otp' => '111111'])
                ->assertSessionHasErrors('otp');
        }

        $this->assertDatabaseHas('password_reset_otps', ['email' => $user->email, 'attempts' => 5]);

        $this->travel(6)->minutes();
        $this->from(route('password.otp'))->post(route('password.otp.verify'), ['otp' => '666666'])
            ->assertSessionHasErrors('otp');
    }

    public function test_request_is_throttled_per_email_and_ip(): void
    {
        config(['auth.enable_mock_otp' => true]);
        $user = User::factory()->create();

        for ($request = 0; $request < 6; $request++) {
            $response = $this->post(route('password.email'), ['email' => $user->email]);
        }

        $response->assertTooManyRequests();
    }

    public function test_reset_page_renders_and_validation_requires_a_confirmed_password(): void
    {
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->withSession([
            'password_reset.verified' => true,
        ])->get(route('password.reset'))->assertOk()->assertViewIs('auth.reset-password');

        $this->withSession([
            'password_reset.verified' => true,
        ])->from(route('password.reset'))->post(route('password.update'), [
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
    }

    public function test_otp_screen_requires_an_email_session_and_renders_six_digit_inputs(): void
    {
        $this->get(route('password.otp'))->assertRedirect(route('password.request'));

        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->get(route('password.otp'))
            ->assertOk()
            ->assertViewIs('auth.password-otp')
            ->assertSee('data-otp-input', false)
            ->assertSeeText('Resend code')
            ->assertSee('Auto verify');
    }

    public function test_forgot_password_can_resend_otp_after_the_cooldown(): void
    {
        config(['auth.enable_mock_otp' => true]);
        Auth::logout();
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $firstFlowId = DB::table('password_reset_otps')->where('user_id', $user->id)->value('flow_id');

        $this->post(route('password.otp.resend'))
            ->assertRedirect(route('password.otp'))
            ->assertSessionHas('status', 'If an account exists for this email, a verification code is available. Please use the latest code.');

        $this->travel(61)->seconds();

        $this->post(route('password.otp.resend'))
            ->assertRedirect(route('password.otp'))
            ->assertSessionHas('status', 'If an account exists for this email, a verification code is available. Please use the latest code.');

        $secondFlowId = DB::table('password_reset_otps')->where('user_id', $user->id)->value('flow_id');

        $this->assertNotSame($firstFlowId, $secondFlowId);
        $this->assertCount(2, Notification::sent($user, PasswordResetOtp::class));
    }

    public function test_otp_expires_independently_of_failed_attempts(): void
    {
        config(['auth.enable_mock_otp' => true]);
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->travel(6)->minutes();

        $this->from(route('password.otp'))
            ->post(route('password.otp.verify'), ['otp' => '666666'])
            ->assertSessionHasErrors('otp');

        $this->assertDatabaseHas('password_reset_otps', [
            'user_id' => $user->id,
            'attempts' => 0,
        ]);
    }

    public function test_stale_reset_notification_cannot_send_after_a_new_code_is_issued(): void
    {
        config(['auth.enable_mock_otp' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->travel(61)->seconds();
        $this->post(route('password.otp.resend'));

        $notifications = Notification::sent($user, PasswordResetOtp::class);
        $this->assertCount(2, $notifications);
        $this->assertFalse($notifications[0]->shouldSend($user, 'mail'));
        $this->assertTrue($notifications[1]->shouldSend($user, 'mail'));
    }

    public function test_delayed_otp_delivery_starts_the_expiry_window_when_sent(): void
    {
        config(['auth.enable_mock_otp' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        DB::table('password_reset_otps')->where('user_id', $user->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $notification = Notification::sent($user, PasswordResetOtp::class)->first();

        $this->assertTrue($notification->shouldSend($user, 'mail'));
        $notification->afterSending($user, 'mail', null);

        $record = DB::table('password_reset_otps')->where('user_id', $user->id)->first();
        $this->assertNotNull($record->delivered_at);
        $this->assertTrue(now()->lessThan($record->expires_at));
    }

    public function test_failed_otp_delivery_removes_the_pending_code(): void
    {
        config(['auth.enable_mock_otp' => true]);
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $notification = Notification::sent($user, PasswordResetOtp::class)->first();

        $notification->failed(new \RuntimeException('SMTP unavailable'));

        $this->assertDatabaseMissing('password_reset_otps', ['user_id' => $user->id]);
    }

    public function test_password_reset_requires_verified_otp(): void
    {
        $user = User::factory()->create();

        $this->withSession(['password_reset.email' => $user->email])
            ->get(route('password.reset'))
            ->assertRedirect(route('password.request'));

        $this->post(route('password.update'), [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('password.request'));
    }
}
