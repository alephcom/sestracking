<?php

namespace Tests\Feature;

use App\Services\SesSuppressionListSyncer;
use Tests\TestCase;

class SyncSesSuppressionListCommandTest extends TestCase
{
    public function test_command_invokes_suppression_list_syncer(): void
    {
        $this->mock(SesSuppressionListSyncer::class, function ($mock): void {
            $mock->shouldReceive('syncAllProjects')->once();
        });

        $this->artisan('ses:sync-suppression-list')->assertSuccessful();
    }
}
