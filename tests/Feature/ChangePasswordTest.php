<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_change_password_and_keeps_current_session(): void
    {
        $currentPassword = 'current-password-123';
        $newPassword = 'new-password-123';
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
    }

    public function test_change_password_requires_authentication_and_valid_current_password(): void
    {
        $this->get(route('password.change'))->assertRedirect(route('login'));

        $user = User::factory()->create(['password' => 'current-password-123']);

        $this->actingAs($user)->from(route('password.change'))->put(route('password.change.update'), [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('current-password-123', $user->fresh()->password));
    }

    public function test_change_password_rejects_reusing_current_password_and_mismatched_confirmation(): void
    {
        $password = 'current-password-123';
        $user = User::factory()->create(['password' => $password]);

        $this->actingAs($user)->from(route('password.change'))->put(route('password.change.update'), [
            'current_password' => $password,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->put(route('password.change.update'), [
            'current_password' => $password,
            'password' => 'different-password-456',
            'password_confirmation' => 'does-not-match-789',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check($password, $user->fresh()->password));
    }

    public function test_changing_password_consumes_its_otp_after_verification(): void
    {
        config(['auth.enable_mock_otp' => true]);
        $user = User::factory()->create(['password' => 'current-password-123']);
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'current-password-123',
            'password' => 'different-password-456',
            'password_confirmation' => 'different-password-456',
        ])->assertRedirect(route('password.change.otp'));

        $this->post(route('password.change.otp.verify'), ['otp' => '666666'])
            ->assertRedirect(route('password.change'));

        $this->assertDatabaseMissing('password_reset_otps', ['email' => $user->email]);
    }

    public function test_change_password_requires_a_valid_otp_before_updating_the_password(): void
    {
        $user = User::factory()->create(['password' => 'current-password-123']);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'current-password-123',
            'password' => 'different-password-456',
            'password_confirmation' => 'different-password-456',
        ]);

        $this->from(route('password.change.otp'))->post(route('password.change.otp.verify'), ['otp' => '111111'])
            ->assertSessionHasErrors('otp');

        $this->assertTrue(Hash::check('current-password-123', $user->fresh()->password));
    }
}
