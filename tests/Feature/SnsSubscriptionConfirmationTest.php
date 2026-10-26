<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SignsSnsMessages;
use Tests\TestCase;

class SnsSubscriptionConfirmationTest extends TestCase
{
    use RefreshDatabase;
    use SignsSnsMessages;

    private const SUBSCRIBE_URL = 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&TopicArn=arn:aws:sns:us-east-1:123456789012:ses&Token=abc';

    protected function setUp(): void
    {
        parent::setUp();

        Project::factory()->create(['token' => 'test-token-123']);
        $this->trustTestSnsCertificate();

        // Confirmations are verified even when notification checks are turned off.
        config(['services.ses.webhook_verify_signatures' => false]);

        Http::fake();
    }

    private function signedConfirmation(array $overrides = []): array
    {
        return $this->signSnsPayload(array_merge([
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'test-message-id',
            'Token' => 'abc',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:ses',
            'Message' => 'You have chosen to subscribe to the topic.',
            'SubscribeURL' => self::SUBSCRIBE_URL,
            'Timestamp' => '2026-10-26T12:00:00.000Z',
        ], $overrides));
    }

    /** @test */
    public function signed_confirmation_is_followed()
    {
        $response = $this->postJson('/webhook/test-token-123', $this->signedConfirmation());

        $response->assertOk();
        $response->assertSeeText('OK');
        Http::assertSent(fn ($request) => $request->url() === self::SUBSCRIBE_URL);
    }

    /** @test */
    public function unsigned_confirmation_is_rejected_without_outbound_request()
    {
        $response = $this->postJson('/webhook/test-token-123', [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'test-message-id',
            'SubscribeURL' => 'http://169.254.169.254/latest/meta-data/',
        ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    /** @test */
    public function tampered_subscribe_url_fails_signature_check()
    {
        $payload = $this->signedConfirmation();
        $payload['SubscribeURL'] = 'http://169.254.169.254/latest/meta-data/';

        $response = $this->postJson('/webhook/test-token-123', $payload);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    /** @test */
    public function signing_cert_outside_aws_is_rejected()
    {
        $payload = $this->signedConfirmation(['SigningCertURL' => 'https://attacker.example.com/cert.pem']);

        $response = $this->postJson('/webhook/test-token-123', $payload);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    /** @test */
    public function signed_confirmation_with_non_sns_subscribe_url_is_rejected()
    {
        $payload = $this->signedConfirmation(['SubscribeURL' => 'https://internal.example.com/admin']);

        $response = $this->postJson('/webhook/test-token-123', $payload);

        $response->assertStatus(400);
        Http::assertNothingSent();
    }

    /** @test */
    public function confirmation_from_disallowed_topic_is_rejected()
    {
        config(['services.ses.webhook_allowed_topic_arns' => ['arn:aws:sns:*:999999999999:*']]);

        $response = $this->postJson('/webhook/test-token-123', $this->signedConfirmation());

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    /** @test */
    public function unknown_project_token_never_triggers_outbound_request()
    {
        $response = $this->postJson('/webhook/no-such-token', $this->signedConfirmation());

        $response->assertNotFound();
        Http::assertNothingSent();
    }
}
