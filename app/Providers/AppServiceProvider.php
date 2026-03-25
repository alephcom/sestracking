<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ProjectAccessService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });

        RateLimiter::for('two-factor-challenge', function (Request $request) {
            $userId = $request->session()->get('login.two_factor_pending_user_id');
            if ($userId !== null && $userId !== '') {
                return Limit::perMinute(12)->by('2fa-challenge:'.(string) $userId.':'.$request->ip());
            }

            return Limit::perMinute(12)->by('2fa-challenge:guest:'.$request->ip());
        });

        RateLimiter::for('two-factor-setup', function (Request $request) {
            $user = $request->user();
            if ($user !== null) {
                return Limit::perMinute(12)->by('2fa-setup:'.$user->getAuthIdentifier().':'.$request->ip());
            }

            return Limit::perMinute(12)->by('2fa-setup:guest:'.$request->ip());
        });
    }
}
