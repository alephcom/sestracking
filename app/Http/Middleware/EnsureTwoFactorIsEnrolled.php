<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsEnrolled
{
    /**
     * Force password / local users to finish TOTP enrollment before using the app.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->requiresInAppTwoFactor()) {
            return $next($request);
        }

        if ($user->hasConfirmedTwoFactor()) {
            return $next($request);
        }

        if ($request->routeIs('two-factor.setup', 'two-factor.setup.confirm', 'logout')) {
            return $next($request);
        }

        return redirect()->route('two-factor.setup');
    }
}
