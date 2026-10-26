<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Project;
use App\Models\RecipientEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookMalformedPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Project::factory()->create(['token' => 'test-token-123']);
    }

    private function validEvent(): array
    {
        return [
            'eventType' => 'Delivery',
            'mail' => [
                'messageId' => 'ses-msg-1',
                'timestamp' => '2026-10-26T12:00:00.000Z',
                'source' => 'sender@example.com',
                'destination' => ['recipient@example.com'],
                'commonHeaders' => ['subject' => 'Hello'],
            ],
            'delivery' => [
                'timestamp' => '2026-10-26T12:00:05.000Z',
                'recipients' => ['recipient@example.com'],
            ],
        ];
    }

    private function postSns(array|string $sesEvent)
    {
        return $this->postJson('/webhook/test-token-123', [
            'Type' => 'Notification',
            'MessageId' => 'sns-'.uniqid(),
            'Message' => is_string($sesEvent) ? $sesEvent : json_encode($sesEvent),
        ]);
    }

    private function without(array $event, string $path): array
    {
        $keys = explode('.', $path);
        $ref = &$event;
        foreach (array_slice($keys, 0, -1) as $key) {
            $ref = &$ref[$key];
        }
        unset($ref[end($keys)]);

        return $event;
    }

    private function with(array $event, string $path, mixed $value): array
    {
        data_set($event, $path, $value);

        return $event;
    }

    public static function malformedEvents(): array
    {
        return [
            'mail missing' => ['without', 'mail', null],
            'messageId missing' => ['without', 'mail.messageId', null],
            'source missing' => ['without', 'mail.source', null],
            'timestamp missing' => ['without', 'mail.timestamp', null],
            'timestamp invalid' => ['with', 'mail.timestamp', 'not-a-date'],
            'event timestamp invalid' => ['with', 'delivery.timestamp', 'not-a-date'],
            'messageId not a string' => ['with', 'mail.messageId', ['nested']],
            'recipients not a list' => ['with', 'delivery.recipients', 'recipient@example.com'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider malformedEvents
     */
    public function malformed_sns_event_returns_400_and_stores_nothing(string $op, string $path, mixed $value)
    {
        $event = $op === 'without' ? $this->without($this->validEvent(), $path) : $this->with($this->validEvent(), $path, $value);

        $this->postSns($event)->assertStatus(400);

        $this->assertSame(0, Email::count());
        $this->assertSame(0, RecipientEvent::count());
    }

    /**
     * @test
     *
     * @dataProvider malformedEvents
     */
    public function malformed_direct_event_returns_400(string $op, string $path, mixed $value)
    {
        $event = $op === 'without' ? $this->without($this->validEvent(), $path) : $this->with($this->validEvent(), $path, $value);

        $this->postJson('/webhook/test-token-123', $event)->assertStatus(400);

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function open_without_any_recipient_returns_400()
    {
        $event = $this->validEvent();
        $event['eventType'] = 'Open';
        unset($event['delivery']);
        $event['mail']['destination'] = [];
        $event['open'] = ['timestamp' => '2026-10-26T12:10:00.000Z'];

        $this->postSns($event)->assertStatus(400);

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function message_field_that_is_not_an_object_returns_400()
    {
        $this->postSns('5')->assertStatus(400);
        $this->postJson('/webhook/test-token-123', ['Type' => 'Notification', 'MessageId' => 'x', 'Message' => ['nested']])
            ->assertStatus(400);
    }

    /** @test */
    public function scalar_json_body_returns_400()
    {
        $this->call('POST', '/webhook/test-token-123', [], [], [], ['CONTENT_TYPE' => 'application/json'], '5')
            ->assertStatus(400);
    }

    /** @test */
    public function unsupported_event_type_is_acknowledged_without_storing()
    {
        $event = $this->validEvent();
        $event['eventType'] = 'SomeFutureEvent';

        $this->postSns($event)->assertOk()->assertSeeText('Ignored unsupported event type');

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function non_string_recipient_entries_are_skipped()
    {
        $event = $this->validEvent();
        $event['delivery']['recipients'] = ['recipient@example.com', ['bogus'], 42];

        $this->postSns($event)->assertOk();

        $this->assertSame(1, RecipientEvent::where('type', 'delivery')->count());
    }

    /** @test */
    public function valid_event_is_still_processed()
    {
        $this->postSns($this->validEvent())->assertOk();

        $this->assertSame(1, Email::count());
        $this->assertSame(1, RecipientEvent::where('type', 'delivery')->count());
    }
}
