<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Failed password attempts allowed per email + IP before a cooldown. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function showLogin()
    {
        return response()->view('auth.login', ['error' => null]);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $throttleKey = 'login:'.Str::lower((string) $request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->view('auth.login', [
                'error' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        if (Auth::attempt($credentials)) {
            RateLimiter::clear($throttleKey);

            /** @var \App\Models\User $user */
            $user = Auth::user();

            // SSO-linked accounts skip in-app TOTP because the identity provider handles MFA.
            // Letting them sign in with a password would bypass both, so require SSO instead.
            if (! $user->requiresInAppTwoFactor()) {
                Auth::logout();

                return response()->view('auth.login', [
                    'error' => 'This account signs in with '.ucfirst((string) $user->provider).'. Please use that button below.',
                ]);
            }

            if (! $user->hasConfirmedTwoFactor()) {
                $request->session()->regenerate();

                return redirect()->intended(route('dashboard.index'));
            }

            $request->session()->put('login.two_factor_pending_user_id', $user->id);
            $request->session()->put('login.two_factor_remember', $request->boolean('remember'));
            Auth::logout();

            return redirect()->route('two-factor.challenge');
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        return response()->view('auth.login', ['error' => 'Invalid email or password.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
