<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_start_google_oauth(): void
    {
        Socialite::fake('google');

        $this->get(route('oauth.redirect', ['provider' => 'google']))
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_google_callback_creates_and_authenticates_a_user(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'email_verified' => true,
            'avatar' => 'https://example.com/ada.jpg',
        ]));

        $response = $this->get(route('oauth.callback', ['provider' => 'google']));

        $response->assertRedirect(route('home'))
            ->assertSessionHas('auth.session_version', 0);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'avatar_url' => 'https://example.com/ada.jpg',
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_github_callback_creates_and_authenticates_a_user(): void
    {
        Socialite::fake('github', SocialiteUser::fake([
            'id' => 'github-123',
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]));

        $response = $this->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'github',
            'provider_id' => 'github-123',
        ]);
    }

    public function test_callback_links_a_provider_to_an_existing_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        Socialite::fake('github', SocialiteUser::fake([
            'id' => 'github-456',
            'email' => 'ADA@EXAMPLE.COM',
        ]));

        $response = $this->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => 'github-456',
        ]);
        $this->assertSame(1, User::query()->where('email', 'ada@example.com')->count());
    }

    public function test_callback_authenticates_a_user_with_an_existing_social_account(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->for($user)->google()->create(['provider_id' => 'google-789']);

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-789',
            'email' => 'different@example.com',
            'email_verified' => true,
        ]));

        $response = $this->get(route('oauth.callback', ['provider' => 'google']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, User::query()->where('email', 'different@example.com')->count());
    }

    public function test_google_callback_rejects_an_unverified_email(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-unverified',
            'email' => 'unverified@example.com',
            'email_verified' => false,
        ]));

        $response = $this->get(route('oauth.callback', ['provider' => 'google']));

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);
    }

    public function test_callback_rejects_a_provider_without_an_email(): void
    {
        Socialite::fake('github', SocialiteUser::fake([
            'id' => 'github-no-email',
            'email' => null,
        ]));

        $response = $this->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');
        $this->assertGuest();
    }

    public function test_callback_rejects_an_inactive_user(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_INACTIVE]);
        SocialAccount::factory()->for($user)->create(['provider_id' => 'github-inactive']);

        Socialite::fake('github', SocialiteUser::fake([
            'id' => 'github-inactive',
            'email' => $user->email,
        ]));

        $response = $this->get(route('oauth.callback', ['provider' => 'github']));

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('oauth');
        $this->assertGuest();
    }

    public function test_callback_honors_the_existing_intended_redirect(): void
    {
        $this->get(route('login', ['redirect' => '/articles/example#comments']))
            ->assertOk();

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-intended',
            'email' => 'intended@example.com',
            'email_verified' => true,
        ]));

        $this->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect('/articles/example#comments');
    }
}
