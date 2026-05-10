<?php

namespace App\Console\Commands;

use App\Services\SesSuppressionListSyncer;
use Illuminate\Console\Command;

class SyncSesSuppressionList extends Command
{
    protected $signature = 'ses:sync-suppression-list';

    protected $description = 'Import the Amazon SES account suppression list into the database for every project that has per-project SES credentials (same work as the daily scheduled job)';

    public function handle(SesSuppressionListSyncer $syncer): int
    {
        $this->info('Syncing SES suppression lists from AWS…');

        $syncer->syncAllProjects();

        $this->info('Suppression list sync finished.');

        return Command::SUCCESS;
    }
}
