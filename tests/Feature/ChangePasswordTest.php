<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_change_password_and_keeps_current_session(): void
    {
        $currentPassword = Str::random(24);
        $newPassword = Str::random(24);
        $user = User::factory()->create(['password' => $currentPassword]);

        $this->actingAs($user)->get(route('password.change'))
            ->assertOk()
            ->assertViewIs('auth.change-password');

        $this->put(route('password.change.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('password.change.otp'))
            ->assertSessionHas('status');

        $this->get(route('password.change.otp'))
            ->assertOk()
            ->assertViewIs('auth.password-otp')
            ->assertSeeText('Confirm password change')
            ->assertSeeText('Resend code');

        $this->post(route('password.change.otp.resend'))
            ->assertRedirect(route('password.change.otp'))
            ->assertSessionHas('status', 'Please wait at least 60 seconds before requesting another code.');

        $this->post(route('password.change.otp.verify'), ['otp' => '666666'])
            ->assertRedirect(route('password.change'))
            ->assertSessionHas('status');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertFalse(Hash::check($currentPassword, $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->session_version);
        $this->get(route('password.change'))->assertOk();
    }

    public function test_change_password_requires_authentication_and_valid_current_password(): void
    {
        $currentPassword = Str::random(24);
        $newPassword = Str::random(24);
        $incorrectPassword = Str::random(24);

        $this->get(route('password.change'))->assertRedirect(route('login'));

        $user = User::factory()->create(['password' => $currentPassword]);

        $this->actingAs($user)->from(route('password.change'))->put(route('password.change.update'), [
            'current_password' => $incorrectPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check($currentPassword, $user->fresh()->password));
    }

    public function test_change_password_rejects_reusing_current_password_and_mismatched_confirmation(): void
    {
        $password = Str::random(24);
        $differentPassword = Str::random(24);
        $mismatchedPassword = Str::random(24);
        $user = User::factory()->create(['password' => $password]);

        $this->actingAs($user)->from(route('password.change'))->put(route('password.change.update'), [
            'current_password' => $password,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->put(route('password.change.update'), [
            'current_password' => $password,
            'password' => $differentPassword,
            'password_confirmation' => $mismatchedPassword,
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check($password, $user->fresh()->password));
    }

    public function test_changing_password_consumes_its_otp_after_verification(): void
    {
        config(['auth.enable_mock_otp' => true]);
        $currentPassword = Str::random(24);
        $newPassword = Str::random(24);
        $user = User::factory()->create(['password' => $currentPassword]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('password.change.otp'));

        $this->post(route('password.change.otp.verify'), ['otp' => '666666'])
            ->assertRedirect(route('password.change'));

        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);
    }

    public function test_change_password_does_not_replace_pending_password_during_resend_cooldown(): void
    {
        config(['auth.enable_mock_otp' => true]);
        $currentPassword = Str::random(24);
        $firstNewPassword = Str::random(24);
        $secondNewPassword = Str::random(24);
        $user = User::factory()->create(['password' => $currentPassword]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => $currentPassword,
            'password' => $firstNewPassword,
            'password_confirmation' => $firstNewPassword,
        ]);

        $this->put(route('password.change.update'), [
            'current_password' => $currentPassword,
            'password' => $secondNewPassword,
            'password_confirmation' => $secondNewPassword,
        ])->assertSessionHas('status');

        $this->post(route('password.change.otp.verify'), ['otp' => '666666'])
            ->assertRedirect(route('password.change'));

        $this->assertTrue(Hash::check($firstNewPassword, $user->fresh()->password));
        $this->assertFalse(Hash::check($secondNewPassword, $user->fresh()->password));
    }

    public function test_change_password_can_resend_a_replacement_otp_after_the_cooldown(): void
    {
        config(['auth.enable_mock_otp' => true]);
        Notification::fake();
        $currentPassword = Str::random(24);
        $newPassword = Str::random(24);
        $user = User::factory()->create(['password' => $currentPassword]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $firstFlowId = DB::table('password_reset_otps')
            ->where('user_id', $user->id)
            ->where('purpose', 'password_change')
            ->value('flow_id');

        $this->travel(61)->seconds();

        $this->post(route('password.change.otp.resend'))
            ->assertRedirect(route('password.change.otp'))
            ->assertSessionHas('status', 'A new verification code has been sent to your email.');

        $secondFlowId = DB::table('password_reset_otps')
            ->where('user_id', $user->id)
            ->where('purpose', 'password_change')
            ->value('flow_id');

        $this->assertNotSame($firstFlowId, $secondFlowId);
        $this->assertCount(2, Notification::sent($user, PasswordResetOtp::class));
    }

    public function test_change_password_requires_a_valid_otp_before_updating_the_password(): void
    {
        $currentPassword = Str::random(24);
        $newPassword = Str::random(24);
        $user = User::factory()->create(['password' => $currentPassword]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $this->from(route('password.change.otp'))->post(route('password.change.otp.verify'), ['otp' => '111111'])
            ->assertSessionHasErrors('otp');

        $this->assertTrue(Hash::check($currentPassword, $user->fresh()->password));
    }

    public function test_session_version_invalidates_an_old_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('password.change'))->assertOk();
        $user->increment('session_version');

        $this->get(route('password.change'))->assertRedirect(route('login'));
    }
}
