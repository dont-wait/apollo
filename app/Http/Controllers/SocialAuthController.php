<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        $provider = $this->resolveProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->resolveProvider($provider);

        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Throwable $exception) {
            Log::warning('Social authentication provider callback failed.', [
                'provider' => $provider,
                'exception' => $exception::class,
            ]);

            return $this->redirectToLoginWithError('We could not connect to the selected sign-in provider. Please try again.');
        }

        if (! $this->hasVerifiedEmail($provider, $providerUser)) {
            return $this->redirectToLoginWithError('This provider did not return a verified email address. Please use another sign-in method.');
        }

        $user = $this->resolveUser($provider, $providerUser);

        if ($user === null || ! $user->isActive()) {
            return $this->redirectToLoginWithError('This account is not available for sign in.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.session_version', $user->session_version);

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('home'));
    }

    private function resolveProvider(string $provider): string
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return $provider;
    }

    private function hasVerifiedEmail(string $provider, SocialiteUser $providerUser): bool
    {
        if (blank($providerUser->getEmail())) {
            return false;
        }

        return match ($provider) {
            'google' => (bool) data_get(
                $providerUser->getRaw(),
                'email_verified',
                data_get($providerUser->getRaw(), 'verified_email', false),
            ),
            'github' => true,
            default => false,
        };
    }

    private function resolveUser(string $provider, SocialiteUser $providerUser): ?User
    {
        $providerId = (string) $providerUser->getId();
        $email = Str::lower(trim((string) $providerUser->getEmail()));

        if (blank($providerId) || blank($email)) {
            return null;
        }

        return DB::transaction(function () use ($email, $provider, $providerId, $providerUser): User {
            $socialAccount = SocialAccount::query()
                ->with('user')
                ->where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();

            if ($socialAccount?->user !== null) {
                return $socialAccount->user;
            }

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($user !== null) {
                if (! $user->isActive()) {
                    return $user;
                }

                $user->socialAccounts()->create([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ]);

                return $user;
            }

            $user = User::create([
                'name' => $this->userName($providerUser, $email),
                'email' => $email,
                'password' => Str::random(64),
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'avatar_url' => $this->avatarUrl($providerUser),
            ])->save();

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $providerId,
            ]);

            return $user;
        });
    }

    private function userName(SocialiteUser $providerUser, string $email): string
    {
        $name = $providerUser->getName() ?: $providerUser->getNickname() ?: Str::before($email, '@');

        return Str::limit($name ?: 'Apollo user', 255, '');
    }

    private function avatarUrl(SocialiteUser $providerUser): ?string
    {
        $avatar = $providerUser->getAvatar();

        return filled($avatar) && filter_var($avatar, FILTER_VALIDATE_URL) ? $avatar : null;
    }

    private function redirectToLoginWithError(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['oauth' => $message]);
    }
}
