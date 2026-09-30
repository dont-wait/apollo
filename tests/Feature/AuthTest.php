<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_renders_the_neurallog_authentication_screen(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertViewIs('auth.login')
            ->assertSeeText('Welcome back to NeuralLog')
            ->assertSeeText('Sign In to Account');
    }

    public function test_register_page_renders_the_account_creation_screen(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertViewIs('auth.register')
            ->assertSeeText('Join NeuralLog Research')
            ->assertSeeText('Initialize Account');
    }

    public function test_valid_registration_creates_and_authenticates_a_user(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('correct-password', $user->password));
    }

    public function test_registration_rejects_invalid_required_fields_and_password_confirmation(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect(route('register'))
            ->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
    }

    public function test_registration_rejects_a_duplicate_email_address(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
        ]);

        $response->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_valid_credentials_authenticate_a_user(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'ada@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_authenticate_a_user(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
