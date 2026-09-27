<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BillingService;
use Illuminate\Console\Command;

/**
 * Bring every paying workspace's Stripe seat count back in line with its
 * members. Seat changes are sent to Stripe as they happen, but a change made
 * while Stripe was unreachable is only logged; this repairs it. Scheduled
 * hourly (routes/console.php).
 *
 * Drift is visible locally: Cashier saves a subscription's quantity only
 * once Stripe has accepted it.
 */
class ReconcileSeats extends Command
{
    protected $signature = 'billing:reconcile-seats {--dry-run : Report workspaces that are out of line without changing them}';

    protected $description = "Update Stripe where a paying workspace's seat count differs from its members";

    public function handle(BillingService $billing): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = $drifted = $failed = 0;

        Tenant::query()->whereHas('subscriptions')->chunkById(100, function ($tenants) use ($billing, $dryRun, &$checked, &$drifted, &$failed) {
            foreach ($tenants as $tenant) {
                if (! $billing->isSubscribed($tenant)) {
                    continue;
                }

                $checked++;
                $billed = $tenant->subscription('default')->quantity;
                $members = $billing->seatsUsed($tenant);

                if ($billed === $members) {
                    continue;
                }

                $drifted++;
                $line = "Workspace {$tenant->id}: billed for {$billed} seats, has {$members} members";

                if ($dryRun) {
                    $this->line($line);

                    continue;
                }

                $billing->syncSeats($tenant);

                if ($tenant->subscription('default')->refresh()->quantity === $members) {
                    $this->line("{$line}: updated.");
                } else {
                    $failed++;
                    $this->warn("{$line}: Stripe did not accept the update (see the log).");
                }
            }
        });

        $this->info("Checked {$checked} paying workspaces; {$drifted} out of line".($dryRun ? ' (dry run, nothing changed).' : ", {$failed} still failing."));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
