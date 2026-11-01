<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Project::factory()->create(['token' => 'rate-limit-token']);
    }

    /** @test */
    public function webhook_returns_429_once_the_per_minute_limit_is_exceeded()
    {
        config(['app.webhook_rate_limit' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/webhook/rate-limit-token', [])->assertStatus(400);
        }

        $this->postJson('/webhook/rate-limit-token', [])->assertStatus(429);
    }

    /** @test */
    public function webhook_limit_is_tracked_per_ip_address()
    {
        config(['app.webhook_rate_limit' => 1]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/webhook/rate-limit-token', [])->assertStatus(400);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/webhook/rate-limit-token', [])->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/webhook/rate-limit-token', [])->assertStatus(400);
    }

    /** @test */
    public function webhook_rate_limit_can_be_disabled()
    {
        config(['app.webhook_rate_limit' => 0]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/webhook/rate-limit-token', [])->assertStatus(400);
        }
    }
}
