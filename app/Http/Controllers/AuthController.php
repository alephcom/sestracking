<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $error = null;
        if (Auth::attempt($credentials)) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (! $user->requiresInAppTwoFactor()) {
                $request->session()->regenerate();

                return redirect()->intended(route('dashboard.index'));
            }

            if (! $user->hasConfirmedTwoFactor()) {
                $request->session()->regenerate();

                return redirect()->intended(route('dashboard.index'));
            }

            $request->session()->put('login.two_factor_pending_user_id', $user->id);
            Auth::logout();

            return redirect()->route('two-factor.challenge');
        } elseif ($request->has('submit')) {
            $error = 'Invalid email or password.';
        }

        return response()->view('auth.login', compact('error'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
