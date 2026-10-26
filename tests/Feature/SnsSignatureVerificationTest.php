<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Project;
use App\Models\RecipientEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsSnsMessages;
use Tests\TestCase;

class SnsSignatureVerificationTest extends TestCase
{
    use RefreshDatabase;
    use SignsSnsMessages;

    private const TOPIC_ARN = 'arn:aws:sns:us-east-1:123456789012:ses-events';

    protected function setUp(): void
    {
        parent::setUp();

        Project::factory()->create(['token' => 'test-token-123']);
        $this->trustTestSnsCertificate();

        config([
            'services.ses.webhook_verify_signatures' => true,
            'services.ses.webhook_allow_direct_payloads' => false,
            'services.ses.webhook_allowed_topic_arns' => [],
        ]);
    }

    private function sesEvent(string $messageId = 'ses-msg-1'): array
    {
        return [
            'eventType' => 'Bounce',
            'mail' => [
                'messageId' => $messageId,
                'timestamp' => '2026-10-26T12:00:00.000Z',
                'source' => 'sender@example.com',
                'destination' => ['recipient@example.com'],
                'commonHeaders' => ['subject' => 'Hello'],
            ],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [['emailAddress' => 'recipient@example.com']],
                'timestamp' => '2026-10-26T12:00:01.000Z',
            ],
        ];
    }

    private function notification(array $overrides = []): array
    {
        return array_merge([
            'Type' => 'Notification',
            'MessageId' => 'sns-msg-1',
            'TopicArn' => self::TOPIC_ARN,
            'Message' => json_encode($this->sesEvent()),
            'Timestamp' => '2026-10-26T12:00:02.000Z',
        ], $overrides);
    }

    /** @test */
    public function signed_notification_is_processed()
    {
        $this->postJson('/webhook/test-token-123', $this->signSnsPayload($this->notification()))
            ->assertOk();

        $this->assertSame(1, RecipientEvent::where('type', 'bounce')->count());
    }

    /** @test */
    public function unsigned_notification_is_rejected()
    {
        $this->postJson('/webhook/test-token-123', $this->notification())
            ->assertStatus(403);

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function notification_with_tampered_message_is_rejected()
    {
        $payload = $this->signSnsPayload($this->notification());
        $payload['Message'] = json_encode($this->sesEvent('forged-msg'));

        $this->postJson('/webhook/test-token-123', $payload)->assertStatus(403);

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function direct_ses_payload_is_rejected_by_default()
    {
        $this->postJson('/webhook/test-token-123', $this->sesEvent())->assertStatus(403);

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function direct_ses_payload_is_accepted_when_explicitly_allowed()
    {
        config(['services.ses.webhook_allow_direct_payloads' => true]);

        $this->postJson('/webhook/test-token-123', $this->sesEvent())->assertOk();

        $this->assertSame(1, Email::count());
    }

    /** @test */
    public function signed_notification_from_disallowed_topic_is_rejected()
    {
        config(['services.ses.webhook_allowed_topic_arns' => ['arn:aws:sns:*:999999999999:*']]);

        $this->postJson('/webhook/test-token-123', $this->signSnsPayload($this->notification()))
            ->assertStatus(403);

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function signed_notification_from_allowed_topic_pattern_is_processed()
    {
        config(['services.ses.webhook_allowed_topic_arns' => ['arn:aws:sns:*:123456789012:*']]);

        $this->postJson('/webhook/test-token-123', $this->signSnsPayload($this->notification()))
            ->assertOk();

        $this->assertSame(1, Email::count());
    }

    /** @test */
    public function signed_unsubscribe_confirmation_is_acknowledged_without_processing()
    {
        $payload = $this->signSnsPayload([
            'Type' => 'UnsubscribeConfirmation',
            'MessageId' => 'unsub-1',
            'Token' => 'abc',
            'TopicArn' => self::TOPIC_ARN,
            'Message' => 'You have chosen to deactivate subscription.',
            'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription',
            'Timestamp' => '2026-10-26T12:00:02.000Z',
        ]);

        $this->postJson('/webhook/test-token-123', $payload)->assertOk();

        $this->assertSame(0, Email::count());
    }
}
