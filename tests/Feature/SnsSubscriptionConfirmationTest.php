<?php

namespace Tests\Feature;

use App\Models\Project;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SnsSubscriptionConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private const CERT_URL = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem';

    private const SUBSCRIBE_URL = 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&TopicArn=arn:aws:sns:us-east-1:123456789012:ses&Token=abc';

    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        Project::factory()->create(['token' => 'test-token-123']);

        // Self-signed key pair standing in for the AWS SNS signing certificate.
        $this->privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $this->privateKey);
        openssl_x509_export(openssl_csr_sign($csr, null, $this->privateKey, 1), $certificatePem);

        $this->app->instance(MessageValidator::class, new MessageValidator(
            fn (string $url) => $url === self::CERT_URL ? $certificatePem : false
        ));

        Http::fake();
    }

    private function signedConfirmation(array $overrides = []): array
    {
        $payload = array_merge([
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'test-message-id',
            'Token' => 'abc',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:ses',
            'Message' => 'You have chosen to subscribe to the topic.',
            'SubscribeURL' => self::SUBSCRIBE_URL,
            'Timestamp' => '2026-10-26T12:00:00.000Z',
            'SignatureVersion' => '2',
            'SigningCertURL' => self::CERT_URL,
        ], $overrides);

        $stringToSign = (new MessageValidator)->getStringToSign(new Message($payload + ['Signature' => 'x']));
        openssl_sign($stringToSign, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $payload['Signature'] = base64_encode($signature);

        return $payload;
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
    public function unknown_project_token_never_triggers_outbound_request()
    {
        $response = $this->postJson('/webhook/no-such-token', $this->signedConfirmation());

        $response->assertNotFound();
        Http::assertNothingSent();
    }
}
