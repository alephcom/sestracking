<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\DashboardCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $projectA;
    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);
        $this->projectA = Project::factory()->create(['token' => 'token-a']);
        $this->projectB = Project::factory()->create(['token' => 'token-b']);
    }

    private function sentCount(Project $project): int
    {
        return $this->actingAs($this->user)->getJson(route('dashboard.api', [
            'projectId' => $project->id,
            'dateFrom' => Carbon::parse('2026-08-01 00:00:00')->toIso8601String(),
            'dateTo' => Carbon::parse('2026-08-01 23:59:59')->toIso8601String(),
            'tzOffset' => 0,
        ]))->assertOk()->json('counters.sent');
    }

    private function postSend(string $token, string $sesMessageId): void
    {
        $this->postJson('/webhook/'.$token, [
            'Type' => 'Notification',
            'MessageId' => 'sns-'.$sesMessageId,
            'Message' => json_encode([
                'eventType' => 'Send',
                'mail' => [
                    'messageId' => $sesMessageId,
                    'source' => 'sender@example.com',
                    'destination' => ['reader@example.com'],
                    'timestamp' => '2026-08-01T10:00:00.000Z',
                    'commonHeaders' => ['subject' => 'Hello'],
                ],
                'send' => ['timestamp' => '2026-08-01T10:00:00.000Z'],
            ]),
        ])->assertOk();
    }

    /** @test */
    public function new_webhook_event_refreshes_that_projects_cached_dashboard(): void
    {
        $this->postSend('token-a', 'a-1');
        $this->assertSame(1, $this->sentCount($this->projectA));

        $this->postSend('token-a', 'a-2');

        $this->assertSame(2, $this->sentCount($this->projectA));
    }

    /** @test */
    public function event_for_another_project_does_not_flush_this_projects_cache(): void
    {
        $this->postSend('token-a', 'a-1');
        $this->assertSame(1, $this->sentCount($this->projectA));

        // Change project A's data behind the cache's back, then send an event to project B
        \App\Models\RecipientEvent::query()->delete();
        $this->postSend('token-b', 'b-1');

        $this->assertSame(1, $this->sentCount($this->projectA), 'project A should still be served from cache');
        $this->assertSame(1, $this->sentCount($this->projectB));
    }

    /** @test */
    public function falls_back_to_plain_ttl_cache_when_store_does_not_support_tags(): void
    {
        config(['cache.default' => 'file']);
        Cache::flush();
        $this->assertFalse(Cache::supportsTags());

        $cache = app(DashboardCache::class);
        $calls = 0;
        $compute = function () use (&$calls) {
            return ++$calls;
        };

        $this->assertSame(1, $cache->remember([$this->projectA->id], 'dashboard-fallback-test', 300, $compute));
        $cache->flushProject($this->projectA->id);
        $this->assertSame(1, $cache->remember([$this->projectA->id], 'dashboard-fallback-test', 300, $compute));

        Cache::forget('dashboard-fallback-test');
    }
}
