<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests should not depend on `npm run build` having been run
        $this->withoutVite();
    }

    /**
     * Mark a password (non-SSO) user as having completed in-app TOTP enrollment.
     */
    protected function enrollAppTwoFactor(User $user): void
    {
        if (! $user->requiresInAppTwoFactor()) {
            return;
        }

        $google2fa = new Google2FA;
        $user->forceFill([
            'two_factor_secret' => $google2fa->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
        ])->save();
    }

    protected function submitTwoFactorChallenge(User $user): void
    {
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp((string) $user->fresh()->two_factor_secret);
        $this->post(route('two-factor.challenge.confirm'), [
            'code' => $code,
        ]);
    }
}
