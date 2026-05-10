<?php

namespace Tests\Feature;

use App\Jobs\PushSuppressedDestinationJob;
use App\Models\Project;
use App\Services\SesSuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class PushSuppressedDestinationJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function job_completes_without_retrying_when_service_throws_invalid_argument(): void
    {
        $this->expectNotToPerformAssertions();

        $project = Project::factory()->create([
            'ses_aws_access_key_id' => 'AKIATESTJOB1',
            'ses_aws_secret_access_key' => 'secret-for-job-test-value',
            'ses_aws_default_region' => 'us-east-1',
        ]);

        $this->mock(SesSuppressionService::class, function ($mock): void {
            $mock->shouldReceive('putSuppressedDestination')
                ->once()
                ->andThrow(new InvalidArgumentException('simulated configuration error'));
        });

        $job = new PushSuppressedDestinationJob($project->id, 'user@example.com', 'BOUNCE');
        $this->app->call([$job, 'handle']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
