<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Console\Command;

/**
 * For someone who lost both their phone and their recovery codes: turns
 * two-factor authentication off so the password alone signs in again. Run
 * it only after confirming who is asking, by a channel other than email to
 * that same address; they should set it up again right away.
 */
class DisableTwoFactor extends Command
{
    protected $signature = 'two-factor:disable {email : The account\'s email address}';

    protected $description = 'Turn off two-factor authentication for an account that lost its device and recovery codes';

    public function handle(TwoFactorService $twoFactor): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No account with that email address.');

            return self::FAILURE;
        }
        if (! $user->hasTwoFactorEnabled()) {
            $this->info('Two-factor authentication is already off for that account.');

            return self::SUCCESS;
        }

        $twoFactor->disable($user);
        $user->signOutEverywhere();
        $this->info("Two-factor authentication is off for {$user->email}, and its sessions and connected apps are signed out.");

        return self::SUCCESS;
    }
}
