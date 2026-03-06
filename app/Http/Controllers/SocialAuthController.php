<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to the OAuth provider's login page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from the OAuth provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'SSO authentication failed. Please try again.',
            ]);
        }

        // Find by provider + provider_id first (returning SSO user)
        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        // Link to existing account by email if SSO columns not set yet
        if (! $user && $socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider'    => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar'      => $socialUser->getAvatar(),
                ]);
            }
        }

        // Create a new account for first-time SSO users
        if (! $user) {
            $user = User::create([
                'name'        => $socialUser->getName() ?? $socialUser->getEmail(),
                'email'       => $socialUser->getEmail(),
                'password'    => null,
                'provider'    => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar'      => $socialUser->getAvatar(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard.index'));
    }
}
