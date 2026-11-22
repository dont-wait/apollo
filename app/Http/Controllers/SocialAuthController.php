<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->resolveProvider($provider);

        if ($request->session()->has('oauth.in_flight')) {
            return $this->redirectToLoginWithError('Another sign-in attempt is already in progress. Please finish it or try again.');
        }

        $request->session()->put('oauth.in_flight', true);

        try {
            return Socialite::driver($provider)->redirect();
        } catch (Throwable $exception) {
            $request->session()->forget('oauth.in_flight');

            Log::warning('Social authentication provider redirect failed.', [
                'provider' => $provider,
                'exception' => $exception::class,
            ]);

            return $this->redirectToLoginWithError('We could not connect to the selected sign-in provider. Please try again.');
        }
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
        } finally {
            $request->session()->forget('oauth.in_flight');
        }

        $providerId = trim((string) $providerUser->getId());
        $linkedUser = $this->findLinkedUser($provider, $providerId);

        if ($linkedUser !== null) {
            return $this->authenticateUser($request, $linkedUser);
        }

        if (blank($providerId) || ! $this->hasVerifiedEmail($provider, $providerUser)) {
            return $this->redirectToLoginWithError('This provider did not return a verified email address. Please use another sign-in method.');
        }

        return $this->authenticateUser($request, $this->resolveUser($provider, $providerUser));
    }

    private function resolveProvider(string $provider): string
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        return $provider;
    }

    /**
     * Socialite's GitHub adapter requests user:email and maps only the primary verified email.
     */
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
            'github' => (bool) data_get($providerUser->getRaw(), 'email_verified', filled($providerUser->getEmail())),
            default => false,
        };
    }

    private function findLinkedUser(string $provider, string $providerId): ?User
    {
        if (blank($providerId)) {
            return null;
        }

        $socialAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        return $socialAccount?->user()->withTrashed()->first();
    }

    private function resolveUser(string $provider, SocialiteUser $providerUser): ?User
    {
        $providerId = (string) $providerUser->getId();
        $email = Str::lower(trim((string) $providerUser->getEmail()));

        if (blank($providerId) || blank($email)) {
            return null;
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return DB::transaction(function () use ($email, $provider, $providerId, $providerUser): ?User {
                    $socialAccount = SocialAccount::query()
                        ->where('provider', $provider)
                        ->where('provider_id', $providerId)
                        ->lockForUpdate()
                        ->first();

                    $linkedUser = $socialAccount?->user()->withTrashed()->first();

                    if ($linkedUser !== null) {
                        return $linkedUser;
                    }

                    $matchingUsers = User::withTrashed()
                        ->whereRaw('LOWER(email) = ?', [$email])
                        ->lockForUpdate()
                        ->limit(2)
                        ->get();

                    if ($matchingUsers->count() > 1) {
                        return null;
                    }

                    $user = $matchingUsers->first();

                    if ($user !== null) {
                        if (! $user->isActive() || $user->trashed()) {
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
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception) || $attempt === 1) {
                    throw $exception;
                }
            }
        }

        return null;
    }

    private function authenticateUser(Request $request, ?User $user): RedirectResponse
    {
        if ($user === null) {
            return $this->redirectToLoginWithError('This account is not available for sign in.');
        }

        $currentUser = User::query()->whereKey($user->getKey())->first();

        if ($currentUser === null || ! $currentUser->isActive()) {
            return $this->redirectToLoginWithError('This account is not available for sign in.');
        }

        Auth::login($currentUser);
        $request->session()->regenerate();
        $request->session()->put('auth.session_version', $currentUser->session_version);

        $currentUser->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('home'));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
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
