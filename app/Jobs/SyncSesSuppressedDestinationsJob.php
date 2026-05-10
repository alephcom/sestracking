<?php

namespace App\Jobs;

use App\Services\SesSuppressionListSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSesSuppressedDestinationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SesSuppressionListSyncer $syncer): void
    {
        $syncer->syncAllProjects();
    }
}
