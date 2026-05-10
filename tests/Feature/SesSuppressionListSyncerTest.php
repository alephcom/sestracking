<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SesSuppressedDestination;
use App\Services\SesSuppressionListSyncer;
use App\Services\SesSuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesSuppressionListSyncerTest extends TestCase
{
    use RefreshDatabase;

    private function projectSesCredentials(): array
    {
        return [
            'ses_aws_access_key_id' => 'test-access-key',
            'ses_aws_secret_access_key' => 'test-secret-for-sync-job',
            'ses_aws_default_region' => 'us-east-1',
        ];
    }

    public function test_two_projects_sharing_same_per_project_credentials_trigger_one_list_call(): void
    {
        config(['services.ses.region' => 'us-east-1']);

        $attrs = $this->projectSesCredentials();
        $p1 = Project::factory()->create($attrs);
        $p2 = Project::factory()->create($attrs);

        $primary = $p1->id < $p2->id ? $p1 : $p2;

        $this->mock(SesSuppressionService::class, function ($mock) use ($primary) {
            $mock->shouldReceive('listSuppressedDestinations')
                ->once()
                ->withArgs(fn ($project, $token, $size) => $project->is($primary) && $token === null && $size === 100)
                ->andReturn([
                    'summaries' => [
                        ['email' => 'Sync@Test.Com', 'reason' => 'BOUNCE', 'last_update_time' => '2026-02-01T00:00:00+00:00'],
                    ],
                    'next_token' => null,
                ]);
        });

        $this->app->make(SesSuppressionListSyncer::class)->syncAllProjects();

        $this->assertDatabaseHas('ses_suppressed_destinations', [
            'project_id' => $p1->id,
            'email' => 'sync@test.com',
            'reason' => 'BOUNCE',
        ]);
        $this->assertDatabaseHas('ses_suppressed_destinations', [
            'project_id' => $p2->id,
            'email' => 'sync@test.com',
        ]);

        $p1->refresh();
        $p2->refresh();
        $this->assertNotNull($p1->ses_suppression_list_synced_at);
        $this->assertNotNull($p2->ses_suppression_list_synced_at);
    }

    public function test_stale_rows_removed_after_successful_full_list(): void
    {
        config(['services.ses.region' => 'us-east-1']);

        $project = Project::factory()->create($this->projectSesCredentials());

        SesSuppressedDestination::query()->create([
            'project_id' => $project->id,
            'email' => 'removed@example.com',
            'reason' => 'BOUNCE',
            'last_update_time' => null,
            'synced_at' => now()->subDays(2),
        ]);

        $this->mock(SesSuppressionService::class, function ($mock) use ($project) {
            $mock->shouldReceive('listSuppressedDestinations')
                ->once()
                ->withArgs(fn ($p, $token, $size) => $p->is($project) && $token === null && $size === 100)
                ->andReturn([
                    'summaries' => [
                        ['email' => 'still@example.com', 'reason' => 'COMPLAINT', 'last_update_time' => null],
                    ],
                    'next_token' => null,
                ]);
        });

        $this->app->make(SesSuppressionListSyncer::class)->syncAllProjects();

        $this->assertDatabaseMissing('ses_suppressed_destinations', ['email' => 'removed@example.com']);
        $this->assertDatabaseHas('ses_suppressed_destinations', ['email' => 'still@example.com']);
    }

    public function test_pagination_follows_next_token(): void
    {
        config(['services.ses.region' => 'us-east-1']);

        $project = Project::factory()->create($this->projectSesCredentials());

        $this->mock(SesSuppressionService::class, function ($mock) use ($project) {
            $mock->shouldReceive('listSuppressedDestinations')
                ->once()
                ->withArgs(fn ($p, $token, $size) => $p->is($project) && $token === null && $size === 100)
                ->andReturn([
                    'summaries' => [
                        ['email' => 'one@example.com', 'reason' => 'BOUNCE', 'last_update_time' => null],
                    ],
                    'next_token' => 'page-2',
                ]);
            $mock->shouldReceive('listSuppressedDestinations')
                ->once()
                ->withArgs(fn ($p, $token, $size) => $p->is($project) && $token === 'page-2' && $size === 100)
                ->andReturn([
                    'summaries' => [
                        ['email' => 'two@example.com', 'reason' => 'BOUNCE', 'last_update_time' => null],
                    ],
                    'next_token' => null,
                ]);
        });

        $this->app->make(SesSuppressionListSyncer::class)->syncAllProjects();

        $this->assertEquals(2, SesSuppressedDestination::query()->where('project_id', $project->id)->count());
    }
}
