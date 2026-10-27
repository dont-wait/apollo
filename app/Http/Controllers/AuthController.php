<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        $redirect = $request->string('redirect')->toString();

        if (
            Str::startsWith($redirect, '/')
            && ! Str::startsWith($redirect, '//')
            && ! Str::startsWith($redirect, '/\\')
        ) {
            $request->session()->put('url.intended', $redirect);
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $remember = $request->boolean('remember');

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Login failed. Please check your email and password and try again.',
            ]);
        }

        $guard = Auth::guard();
        $recallerName = $guard->getRecallerName();

        if (! $remember && ! is_null($request->cookies->get($recallerName))) {
            $guard->getCookieJar()->queue($guard->getCookieJar()->forget($recallerName));
        }

        $request->session()->regenerate();
        $request->session()->put('auth.session_version', $request->user()->session_version);

        return redirect()->intended(route('home'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only([
            'name',
            'email',
            'password',
        ]));

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.session_version', $user->session_version);

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
