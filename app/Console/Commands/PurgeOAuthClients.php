<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;

/**
 * Delete MCP clients that registered themselves (dynamic client
 * registration, open to anyone) but that nobody ever allowed in. Without it
 * the table only grows. Scheduled daily (routes/console.php).
 */
class PurgeOAuthClients extends Command
{
    protected $signature = 'oauth:purge-clients {--hours=24 : Keep clients registered within this many hours, still on their way through consent}';

    protected $description = 'Delete OAuth clients that registered but were never authorized';

    public function handle(): int
    {
        $deleted = Passport::client()->newQuery()
            ->whereNull('authorized_at')
            ->whereNull('owner_id')
            ->where('grant_types', 'like', '%authorization_code%')
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->delete();

        $this->components->info("Deleted {$deleted} OAuth clients that were never authorized.");

        return self::SUCCESS;
    }
}
