<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Project;
use App\Models\RecipientEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookOpenClickDedupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Project::factory()->create(['token' => 'test-token-123']);
    }

    private function notification(string $eventType, string $snsMessageId, array $destination): array
    {
        $key = strtolower($eventType);

        return [
            'Type' => 'Notification',
            'MessageId' => $snsMessageId,
            'Message' => json_encode([
                'eventType' => $eventType,
                'mail' => [
                    'messageId' => 'ses-msg-1',
                    'timestamp' => '2026-10-26T12:00:00.000Z',
                    'source' => 'sender@example.com',
                    'destination' => $destination,
                    'commonHeaders' => ['subject' => 'Hello'],
                ],
                $key => ['timestamp' => '2026-10-26T12:05:00.000Z'],
            ]),
        ];
    }

    public static function eventTypes(): array
    {
        return [
            'open' => ['Open', 'opens'],
            'click' => ['Click', 'clicks'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider eventTypes
     */
    public function sns_retry_does_not_double_count_single_recipient(string $eventType, string $counter)
    {
        $payload = $this->notification($eventType, 'sns-1', ['only@example.com']);

        $this->postJson('/webhook/test-token-123', $payload)->assertOk();
        $this->postJson('/webhook/test-token-123', $payload)->assertOk();

        $this->assertSame(1, Email::first()->{$counter});
        $this->assertSame(1, RecipientEvent::where('type', strtolower($eventType))->count());
    }

    /**
     * @test
     *
     * @dataProvider eventTypes
     */
    public function sns_retry_does_not_double_count_multi_recipient(string $eventType, string $counter)
    {
        $payload = $this->notification($eventType, 'sns-1', ['a@example.com', 'b@example.com']);

        $this->postJson('/webhook/test-token-123', $payload)->assertOk();
        $this->postJson('/webhook/test-token-123', $payload)->assertOk();

        $this->assertSame(1, Email::first()->{$counter});
        $this->assertSame(1, RecipientEvent::where('type', strtolower($eventType))->count());
    }

    /**
     * @test
     *
     * @dataProvider eventTypes
     */
    public function distinct_events_are_still_counted(string $eventType, string $counter)
    {
        $destination = ['a@example.com', 'b@example.com'];

        $this->postJson('/webhook/test-token-123', $this->notification($eventType, 'sns-1', $destination))->assertOk();
        $this->postJson('/webhook/test-token-123', $this->notification($eventType, 'sns-2', $destination))->assertOk();
        $this->postJson('/webhook/test-token-123', $this->notification($eventType, 'sns-3', $destination))->assertOk();

        $this->assertSame(3, Email::first()->{$counter});
        $this->assertSame(3, RecipientEvent::where('type', strtolower($eventType))->count());
    }
}
