<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\Middleware\AuthenticateSession as FrameworkAuthenticateSession;

class AuthenticateSession extends FrameworkAuthenticateSession
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($request->hasSession() && $user?->getAuthPassword()) {
            $sessionVersion = $request->session()->get('auth.session_version');
            $userVersion = (int) $user->session_version;

            if ($sessionVersion === null && $userVersion > 0) {
                $this->logout($request);
            }

            if ($sessionVersion !== null && (int) $sessionVersion !== $userVersion) {
                $this->logout($request);
            }

            $request->session()->put('auth.session_version', $userVersion);
        }

        return parent::handle($request, $next);
    }
}
