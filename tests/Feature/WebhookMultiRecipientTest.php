<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\RecipientEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookMultiRecipientTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['token' => 'test-token-123']);
    }

    /** @test */
    public function webhook_handles_open_event_with_multiple_recipients_correctly()
    {
        // First, send a message to 2 recipients
        $sendPayload = $this->createSesNotification('send', [
            'mail' => [
                'messageId' => 'multi-recipient-test-123',
                'source' => 'test@example.com',
                'destination' => ['recipient1@example.com', 'recipient2@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Multi-recipient Test Email'
                ]
            ]
        ]);

        $this->postJson('/webhook/test-token-123', $sendPayload);

        // Verify email and recipients were created
        $email = Email::where('message_id', 'multi-recipient-test-123')->first();
        $this->assertNotNull($email);
        $this->assertEquals(2, $email->recipients()->count());
        $this->assertEquals(0, $email->opens);

        // Now send an open event with both recipients in destination (simulating SES behavior)
        $openPayload = $this->createSesNotification('open', [
            'mail' => [
                'messageId' => 'multi-recipient-test-123',
                'source' => 'test@example.com',
                'destination' => ['recipient1@example.com', 'recipient2@example.com'], // SES includes both
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Multi-recipient Test Email'
                ]
            ],
            'open' => [
                'timestamp' => '2025-01-01T10:01:00.000Z'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $openPayload);
        $response->assertOk();

        // Verify only ONE open event was created (assigned to first available recipient)
        $email->refresh();
        $this->assertEquals(1, $email->opens);
        $this->assertEquals(1, RecipientEvent::where('type', 'open')->count());

        // Verify the event was assigned to one specific recipient
        $openEvent = RecipientEvent::where('type', 'open')->first();
        $this->assertNotNull($openEvent);
        $this->assertContains($openEvent->recipient->address, ['recipient1@example.com', 'recipient2@example.com']);
    }

    /** @test */
    public function webhook_handles_multiple_open_events_with_multiple_recipients()
    {
        // Create email with 2 recipients
        $email = Email::factory()->create([
            'project_id' => $this->project->id,
            'message_id' => 'multi-open-test-123',
            'opens' => 0
        ]);
        
        EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'recipient1@example.com'
        ]);
        
        EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'recipient2@example.com'
        ]);

        // Send first open event
        $openPayload1 = $this->createSesNotification('open', [
            'mail' => [
                'messageId' => 'multi-open-test-123',
                'source' => 'test@example.com',
                'destination' => ['recipient1@example.com', 'recipient2@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ],
            'open' => [
                'timestamp' => '2025-01-01T10:01:00.000Z'
            ]
        ]);

        $this->postJson('/webhook/test-token-123', $openPayload1);

        // Send second open event (different SNS message ID)
        $openPayload2 = $this->createSesNotification('open', [
            'mail' => [
                'messageId' => 'multi-open-test-123',
                'source' => 'test@example.com',
                'destination' => ['recipient1@example.com', 'recipient2@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ],
            'open' => [
                'timestamp' => '2025-01-01T10:02:00.000Z'
            ]
        ], 'different-sns-id-123');

        $this->postJson('/webhook/test-token-123', $openPayload2);

        // Verify both opens were counted
        $email->refresh();
        $this->assertEquals(2, $email->opens);
        $this->assertEquals(2, RecipientEvent::where('type', 'open')->count());

        // Verify events were assigned to different recipients
        $openEvents = RecipientEvent::where('type', 'open')->get();
        $assignedRecipients = $openEvents->pluck('recipient.address')->toArray();
        $this->assertCount(2, array_unique($assignedRecipients));
    }

    /** @test */
    public function webhook_handles_click_event_with_multiple_recipients_correctly()
    {
        // Create email with 2 recipients
        $email = Email::factory()->create([
            'project_id' => $this->project->id,
            'message_id' => 'multi-click-test-123',
            'clicks' => 0
        ]);
        
        EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'recipient1@example.com'
        ]);
        
        EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'recipient2@example.com'
        ]);

        // Send click event with both recipients in destination
        $clickPayload = $this->createSesNotification('click', [
            'mail' => [
                'messageId' => 'multi-click-test-123',
                'source' => 'test@example.com',
                'destination' => ['recipient1@example.com', 'recipient2@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ],
            'click' => [
                'timestamp' => '2025-01-01T10:01:00.000Z',
                'link' => 'https://example.com/link'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $clickPayload);
        $response->assertOk();

        // Verify only ONE click event was created and counted
        $email->refresh();
        $this->assertEquals(1, $email->clicks);
        $this->assertEquals(1, RecipientEvent::where('type', 'click')->count());

        // Verify the event was assigned to one specific recipient
        $clickEvent = RecipientEvent::where('type', 'click')->first();
        $this->assertNotNull($clickEvent);
        $this->assertContains($clickEvent->recipient->address, ['recipient1@example.com', 'recipient2@example.com']);
    }

    private function createSesNotification(string $eventType, array $sesData, string $messageId = null): array
    {
        $messageId = $messageId ?? 'sns-message-' . uniqid() . '-' . mt_rand(1000, 9999);
        
        return [
            'Type' => 'Notification',
            'MessageId' => $messageId,
            'Message' => json_encode(array_merge([
                'eventType' => $eventType
            ], $sesData))
        ];
    }
}