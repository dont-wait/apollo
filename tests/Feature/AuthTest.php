<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_renders_the_apollo_blog_authentication_screen(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertViewIs('auth.login')
            ->assertSeeText('Welcome back to Apollo Blog')
            ->assertSeeText('Continue with GitHub')
            ->assertSeeText('Continue with Google')
            ->assertSeeText('Sign In to Account')
            ->assertDontSee('checked');
    }

    public function test_register_page_renders_the_account_creation_screen(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertViewIs('auth.register')
            ->assertSeeText('Join Apollo Blog')
            ->assertSeeText('Initialize Account');
    }

    public function test_valid_registration_creates_and_authenticates_a_user(): void
    {
        $password = fake()->password(minLength: 12);

        $response = $this->post(route('register.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'role' => 'USER',
        ]);

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

        $this->assertTrue(Hash::check($password, $user->password));
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
        $password = fake()->password(minLength: 12);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_valid_credentials_authenticate_a_user(): void
    {
        $password = fake()->password(minLength: 12);

        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'ada@example.com',
            'password' => $password,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
    }

    public function test_login_remembers_a_user_only_when_requested(): void
    {
        $password = fake()->password(minLength: 12);

        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'ada@example.com',
            'password' => $password,
            'remember' => true,
        ]);

        $response->assertRedirect(route('home'))
            ->assertCookieNotExpired(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_without_remember_expires_a_previously_issued_recaller_cookie(): void
    {
        $password = fake()->password(minLength: 12);

        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make($password),
        ]);

        $recallerName = Auth::guard()->getRecallerName();

        $remembered = $this->post(route('login.store'), [
            'email' => 'ada@example.com',
            'password' => $password,
            'remember' => true,
        ]);

        $remembered->assertRedirect(route('home'))
            ->assertCookieNotExpired($recallerName);

        $recaller = $remembered->getCookie($recallerName);

        $this->flushSession();
        Auth::forgetGuards();

        $response = $this->withoutMiddleware(RedirectIfAuthenticated::class)
            ->withCookie($recallerName, $recaller->getValue())
            ->post(route('login.store'), [
                'email' => 'ada@example.com',
                'password' => $password,
            ]);

        $response->assertRedirect(route('home'))
            ->assertCookieExpired($recallerName);
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_authenticate_a_user(): void
    {
        $validPassword = fake()->password(minLength: 12);
        $invalidPassword = fake()->password(minLength: 12);

        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make($validPassword),
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'ada@example.com',
            'password' => $invalidPassword,
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
