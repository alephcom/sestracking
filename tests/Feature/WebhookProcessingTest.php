<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\RecipientEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookProcessingTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['token' => 'test-token-123']);
    }

    /** @test */
    public function webhook_handles_sns_subscription_confirmation()
    {
        Http::fake();

        $confirmationPayload = [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'test-message-id',
            'SubscribeURL' => 'https://sns.amazonaws.com/subscription-confirm-url'
        ];

        $response = $this->postJson('/webhook/test-token-123', $confirmationPayload);

        $response->assertOk();
        $response->assertSeeText('OK');
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://sns.amazonaws.com/subscription-confirm-url';
        });
    }

    /** @test */
    public function webhook_validates_sns_message_structure()
    {
        $response = $this->call('POST', '/webhook/test-token-123', [], [], [], [], 'invalid-json');
        
        $response->assertStatus(400);
        $response->assertSeeText('Bad JSON');
    }

    /** @test */
    public function webhook_processes_send_events()
    {
        $sendPayload = $this->createSesNotification('send', [
            'mail' => [
                'messageId' => 'send-message-123',
                'source' => 'test@example.com',
                'destination' => ['recipient@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $sendPayload);

        $response->assertOk();
        $response->assertSeeText('OK');

        // Verify email was created
        $this->assertDatabaseHas('emails', [
            'project_id' => $this->project->id,
            'message_id' => 'send-message-123',
            'source' => 'test@example.com',
            'subject' => 'Test Email Subject'
        ]);

        // Verify recipient was created
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'recipient@example.com'
        ]);

        // Verify event was recorded
        $this->assertDatabaseHas('recipient_events', [
            'type' => 'send'
        ]);
    }

    /** @test */
    public function webhook_processes_delivery_events()
    {
        $deliveryPayload = $this->createSesNotification('delivery', [
            'mail' => [
                'messageId' => 'delivery-message-123',
                'source' => 'test@example.com',
                'destination' => ['recipient@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ],
            'delivery' => [
                'timestamp' => '2025-01-01T10:01:00.000Z',
                'recipients' => ['recipient@example.com']
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $deliveryPayload);

        $response->assertOk();

        // Verify recipient status was updated
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'recipient@example.com',
            'status' => 'delivered'
        ]);

        // Verify event was recorded
        $this->assertDatabaseHas('recipient_events', [
            'type' => 'delivery'
        ]);
    }

    /** @test */
    public function webhook_processes_bounce_events()
    {
        $bouncePayload = $this->createSesNotification('bounce', [
            'mail' => [
                'messageId' => 'bounce-message-123',
                'source' => 'test@example.com',
                'destination' => ['bounced@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ],
            'bounce' => [
                'timestamp' => '2025-01-01T10:02:00.000Z',
                'bounceType' => 'Permanent'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $bouncePayload);

        $response->assertOk();

        // Verify recipient status was updated
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'bounced@example.com',
            'status' => 'bounced'
        ]);

        // Verify event was recorded
        $this->assertDatabaseHas('recipient_events', [
            'type' => 'bounce'
        ]);
    }

    /** @test */
    public function webhook_processes_complaint_events()
    {
        $complaintPayload = $this->createSesNotification('complaint', [
            'mail' => [
                'messageId' => 'complaint-message-123',
                'source' => 'test@example.com',
                'destination' => ['complainer@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ],
            'complaint' => [
                'timestamp' => '2025-01-01T10:03:00.000Z',
                'complaintFeedbackType' => 'abuse'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $complaintPayload);

        $response->assertOk();

        // Verify recipient status was updated
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'complainer@example.com',
            'status' => 'complained'
        ]);

        // Verify event was recorded
        $this->assertDatabaseHas('recipient_events', [
            'type' => 'complaint'
        ]);
    }

    /** @test */
    public function webhook_processes_open_events()
    {
        // First create an email
        $email = Email::factory()->create([
            'project_id' => $this->project->id,
            'message_id' => 'open-message-123',
            'opens' => 0
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'opener@example.com'
        ]);

        $openPayload = $this->createSesNotification('open', [
            'mail' => [
                'messageId' => 'open-message-123',
                'source' => 'test@example.com',
                'destination' => ['opener@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ],
            'open' => [
                'timestamp' => '2025-01-01T10:04:00.000Z'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $openPayload);

        $response->assertOk();

        // Verify opens counter was incremented
        $email->refresh();
        $this->assertEquals(1, $email->opens);

        // Verify event was recorded
        $this->assertDatabaseHas('recipient_events', [
            'type' => 'open',
            'recipient_id' => $recipient->id
        ]);
    }

    /** @test */
    public function webhook_processes_click_events()
    {
        // First create an email
        $email = Email::factory()->create([
            'project_id' => $this->project->id,
            'message_id' => 'click-message-123',
            'clicks' => 0
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'clicker@example.com'
        ]);

        $clickPayload = $this->createSesNotification('click', [
            'mail' => [
                'messageId' => 'click-message-123',
                'source' => 'test@example.com',
                'destination' => ['clicker@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ],
            'click' => [
                'timestamp' => '2025-01-01T10:05:00.000Z',
                'link' => 'https://example.com/link'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $clickPayload);

        $response->assertOk();

        // Verify clicks counter was incremented
        $email->refresh();
        $this->assertEquals(1, $email->clicks);

        // Verify event was recorded
        $this->assertDatabaseHas('recipient_events', [
            'type' => 'click',
            'recipient_id' => $recipient->id
        ]);
    }

    /** @test */
    public function webhook_processes_reject_events()
    {
        $rejectPayload = $this->createSesNotification('reject', [
            'mail' => [
                'messageId' => 'reject-message-123',
                'source' => 'test@example.com',
                'destination' => ['rejected@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ],
            'reject' => [
                'reason' => 'Bad reputation'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $rejectPayload);

        $response->assertOk();

        // Verify recipient status was updated
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'rejected@example.com',
            'status' => 'bounced'
        ]);

        // Verify event was recorded
        $this->assertDatabaseHas('recipient_events', [
            'type' => 'reject'
        ]);
    }

    /** @test */
    public function webhook_processes_rendering_failure_events()
    {
        $renderingFailurePayload = $this->createSesNotification('rendering_failure', [
            'mail' => [
                'messageId' => 'rendering-failure-message-123',
                'source' => 'test@example.com',
                'destination' => ['failed@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ],
            'rendering_failure' => [
                'templateName' => 'TestTemplate',
                'errorMessage' => 'Template rendering failed'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $renderingFailurePayload);

        $response->assertOk();

        // Verify recipient status was updated
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'failed@example.com',
            'status' => 'bounced'
        ]);

        // Verify event was recorded
        $this->assertDatabaseHas('recipient_events', [
            'type' => 'rendering_failure'
        ]);
    }

    /** @test */
    public function webhook_validates_project_token()
    {
        $payload = $this->createSesNotification('send', [
            'mail' => [
                'messageId' => 'test-message-123',
                'source' => 'test@example.com',
                'destination' => ['recipient@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ]
        ]);

        // Test with invalid token
        $response = $this->postJson('/webhook/invalid-token', $payload);
        $response->assertStatus(404);
    }

    /** @test */
    public function webhook_rejects_invalid_tokens()
    {
        $payload = $this->createSesNotification('send', [
            'mail' => [
                'messageId' => 'test-message-123',
                'source' => 'test@example.com',
                'destination' => ['recipient@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ]
        ]);

        $response = $this->postJson('/webhook/nonexistent-token', $payload);
        $response->assertStatus(404);
    }

    /** @test */
    public function webhook_validates_project_exists()
    {
        // Delete the project to test validation
        $this->project->delete();

        $payload = $this->createSesNotification('send', [
            'mail' => [
                'messageId' => 'test-message-123',
                'source' => 'test@example.com',
                'destination' => ['recipient@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $payload);
        $response->assertStatus(404);
    }

    /** @test */
    public function webhook_creates_email_records_correctly()
    {
        $payload = $this->createSesNotification('send', [
            'mail' => [
                'messageId' => 'create-test-123',
                'source' => 'sender@example.com',
                'destination' => ['recipient@example.com'],
                'timestamp' => '2025-01-01T12:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Test Email Subject'
                ]
            ]
        ]);

        $response = $this->postJson('/webhook/test-token-123', $payload);

        $response->assertOk();

        // Verify email record
        $this->assertDatabaseHas('emails', [
            'project_id' => $this->project->id,
            'message_id' => 'create-test-123',
            'source' => 'sender@example.com',
            'subject' => 'Test Email Subject'
        ]);

        // Verify recipient
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'recipient@example.com'
        ]);
    }

    /** @test */
    public function webhook_creates_multiple_recipients_correctly()
    {
        // Test multiple recipients in separate requests to avoid SNS message ID collision
        $payload1 = $this->createSesNotification('send', [
            'mail' => [
                'messageId' => 'multi-test-123',
                'source' => 'sender@example.com',
                'destination' => ['recipient1@example.com'],
                'timestamp' => '2025-01-01T12:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Multi-recipient Test Email'
                ]
            ]
        ]);

        $payload2 = $this->createSesNotification('delivery', [
            'mail' => [
                'messageId' => 'multi-test-123',
                'source' => 'sender@example.com',
                'destination' => ['recipient2@example.com'],
                'timestamp' => '2025-01-01T12:00:00.000Z',
                'commonHeaders' => [
                    'subject' => 'Multi-recipient Test Email'
                ]
            ],
            'delivery' => [
                'timestamp' => '2025-01-01T12:01:00.000Z',
                'recipients' => ['recipient2@example.com']
            ]
        ]);

        $this->postJson('/webhook/test-token-123', $payload1);
        $this->postJson('/webhook/test-token-123', $payload2);

        // Verify single email record (same message_id)
        $this->assertEquals(1, Email::where('message_id', 'multi-test-123')->count());

        // Verify multiple recipients
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'recipient1@example.com'
        ]);
        $this->assertDatabaseHas('email_recipients', [
            'address' => 'recipient2@example.com'
        ]);
    }

    /** @test */
    public function webhook_handles_duplicate_events()
    {
        $messageId = 'duplicate-test-123';
        
        // Create initial event
        $event = RecipientEvent::factory()->create([
            'sns_message_id' => $messageId
        ]);

        $payload = $this->createSesNotification('send', [
            'mail' => [
                'messageId' => 'some-email-123',
                'source' => 'test@example.com',
                'destination' => ['recipient@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ]
        ], $messageId);

        $response = $this->postJson('/webhook/test-token-123', $payload);

        $response->assertOk();
        $response->assertSeeText('Duplicate OK');

        // Verify no additional events were created
        $this->assertEquals(1, RecipientEvent::where('sns_message_id', $messageId)->count());
    }

    /** @test */
    public function webhook_handles_malformed_json()
    {
        $response = $this->call('POST', '/webhook/test-token-123', [], [], [], [], '{invalid-json');

        $response->assertStatus(400);
        $response->assertSeeText('Bad JSON');
    }

    /** @test */
    public function webhook_updates_email_counters()
    {
        $email = Email::factory()->create([
            'project_id' => $this->project->id,
            'message_id' => 'counter-test-123',
            'opens' => 5,
            'clicks' => 3
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'counter@example.com'
        ]);

        // Test open increment
        $openPayload = $this->createSesNotification('open', [
            'mail' => [
                'messageId' => 'counter-test-123',
                'source' => 'test@example.com',
                'destination' => ['counter@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ],
            'open' => [
                'timestamp' => '2025-01-01T10:04:00.000Z'
            ]
        ]);

        $this->postJson('/webhook/test-token-123', $openPayload);
        
        $email->refresh();
        $this->assertEquals(6, $email->opens);
        $this->assertEquals(3, $email->clicks);

        // Test click increment
        $clickPayload = $this->createSesNotification('click', [
            'mail' => [
                'messageId' => 'counter-test-123',
                'source' => 'test@example.com',
                'destination' => ['counter@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z'
            ],
            'click' => [
                'timestamp' => '2025-01-01T10:05:00.000Z',
                'link' => 'https://example.com'
            ]
        ], 'different-sns-message-id');

        $this->postJson('/webhook/test-token-123', $clickPayload);
        
        $email->refresh();
        $this->assertEquals(6, $email->opens);
        $this->assertEquals(4, $email->clicks);
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