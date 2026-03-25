<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetUserTwoFactor extends Command
{
    protected $signature = 'user:reset-two-factor
                            {identifier : The user email or ID}';

    protected $description = 'Clear in-app two-factor authentication for a user (forces re-enrollment on next password sign-in)';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        $user = User::where('id', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user) {
            $this->error("User not found: {$identifier}");

            return Command::FAILURE;
        }

        if (! $user->requiresInAppTwoFactor()) {
            $this->warn("User '{$user->email}' uses SSO and does not have in-app two-factor to reset.");

            return Command::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("✓ Cleared two-factor authentication for user: {$user->email} (ID: {$user->id})");
        $this->info('  On next email/password sign-in they will set up the authenticator again (and see new recovery codes).');

        return Command::SUCCESS;
    }
}
