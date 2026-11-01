<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSnsSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const CERT_URL = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem';

    private const SUBSCRIBE_URL = 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&Token=abc';

    private \OpenSSLAsymmetricKey $privateKey;

    private string $certPem = '';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Project::factory()->create(['token' => 'signed-token']);

        // A throwaway key and self-signed certificate standing in for AWS's signing certificate
        $this->privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $this->privateKey);
        openssl_x509_export(openssl_csr_sign($csr, null, $this->privateKey, 1), $this->certPem);

        Http::fake([
            self::CERT_URL => Http::response($this->certPem),
            self::SUBSCRIBE_URL => Http::response('<ConfirmSubscriptionResponse/>'),
        ]);
    }

    private function sign(array $message, string $version = '2', array $keys = []): array
    {
        $keys = $keys ?: ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
        $stringToSign = '';
        foreach ($keys as $key) {
            if (array_key_exists($key, $message)) {
                $stringToSign .= $key."\n".$message[$key]."\n";
            }
        }
        openssl_sign($stringToSign, $signature, $this->privateKey, $version === '1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256);

        return $message + [
            'SignatureVersion' => $version,
            'Signature' => base64_encode($signature),
            'SigningCertURL' => self::CERT_URL,
        ];
    }

    private function notification(string $messageId = 'sns-signed-1'): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => $messageId,
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:ses-events',
            'Timestamp' => '2026-11-01T10:00:00.000Z',
            'Message' => json_encode([
                'eventType' => 'Delivery',
                'mail' => [
                    'messageId' => 'ses-'.$messageId,
                    'source' => 'sender@example.com',
                    'destination' => ['reader@example.com'],
                    'timestamp' => '2026-11-01T10:00:00.000Z',
                    'commonHeaders' => ['subject' => 'Signed'],
                ],
                'delivery' => ['timestamp' => '2026-11-01T10:00:05.000Z', 'recipients' => ['reader@example.com']],
            ]),
        ];
    }

    /** @test */
    public function accepts_a_notification_signed_with_signature_version_2(): void
    {
        $this->postJson('/webhook/signed-token', $this->sign($this->notification()))->assertOk();

        $this->assertSame(1, Email::count());
    }

    /** @test */
    public function accepts_a_notification_signed_with_signature_version_1(): void
    {
        $this->postJson('/webhook/signed-token', $this->sign($this->notification(), '1'))->assertOk();

        $this->assertSame(1, Email::count());
    }

    /** @test */
    public function rejects_a_notification_whose_message_was_changed_after_signing(): void
    {
        $payload = $this->sign($this->notification());
        $payload['Message'] = str_replace('reader@example.com', 'attacker@example.com', $payload['Message']);

        $this->postJson('/webhook/signed-token', $payload)->assertForbidden();

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function accepts_a_signed_notification_with_a_null_subject(): void
    {
        // AWS leaves Subject out of the string to sign when it is null
        $payload = $this->sign($this->notification());
        $payload['Subject'] = null;

        $this->postJson('/webhook/signed-token', $payload)->assertOk();
    }

    /** @test */
    public function rejects_a_signed_field_that_is_not_a_string_without_erroring(): void
    {
        $payload = $this->sign($this->notification());
        $payload['TopicArn'] = ['not', 'a', 'string'];

        $this->postJson('/webhook/signed-token', $payload)->assertForbidden();
    }

    /** @test */
    public function rejects_an_unsigned_notification(): void
    {
        $this->postJson('/webhook/signed-token', $this->notification())->assertForbidden();

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function rejects_a_direct_ses_payload_without_the_bypass_setting(): void
    {
        $direct = json_decode($this->notification()['Message'], true);

        $this->postJson('/webhook/signed-token', $direct)->assertForbidden();

        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function rejects_a_signing_certificate_that_is_not_hosted_by_sns_and_never_fetches_it(): void
    {
        foreach ([
            'https://evil.example.com/SimpleNotificationService-test.pem',
            'https://sns.us-east-1.amazonaws.com.evil.example.com/cert.pem',
            'http://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem',
            'https://sns.us-east-1.amazonaws.com:8443/SimpleNotificationService-test.pem',
            'https://sns.us-east-1.amazonaws.com/not-a-cert.txt',
        ] as $certUrl) {
            $payload = $this->sign($this->notification());
            $payload['SigningCertURL'] = $certUrl;

            $this->postJson('/webhook/signed-token', $payload)->assertForbidden();
        }

        Http::assertNothingSent();
    }

    /** @test */
    public function caches_the_signing_certificate_between_requests(): void
    {
        $this->postJson('/webhook/signed-token', $this->sign($this->notification('sns-a')))->assertOk();
        $this->postJson('/webhook/signed-token', $this->sign($this->notification('sns-b')))->assertOk();

        Http::assertSentCount(1);
    }

    /** @test */
    public function confirms_a_signed_subscription_confirmation(): void
    {
        $confirmation = $this->sign([
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'sub-1',
            'Token' => 'abc',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:ses-events',
            'Message' => 'You have chosen to subscribe to the topic.',
            'SubscribeURL' => self::SUBSCRIBE_URL,
            'Timestamp' => '2026-11-01T10:00:00.000Z',
        ], '2', ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type']);

        $this->postJson('/webhook/signed-token', $confirmation)->assertOk();

        Http::assertSent(fn ($request) => $request->url() === self::SUBSCRIBE_URL);
    }

    /** @test */
    public function rejects_an_unsigned_subscription_confirmation_without_visiting_its_url(): void
    {
        $this->postJson('/webhook/signed-token', [
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => 'sub-forged',
            'SubscribeURL' => 'http://169.254.169.254/latest/meta-data/',
        ])->assertForbidden();

        Http::assertNothingSent();
    }

    /** @test */
    public function project_with_bypass_enabled_accepts_unsigned_direct_ses_payloads(): void
    {
        Project::factory()->allowingUnsignedWebhooks()->create(['token' => 'unsigned-token']);
        $direct = json_decode($this->notification()['Message'], true);

        $this->postJson('/webhook/unsigned-token', $direct)->assertOk();

        $this->assertSame(1, Email::count());
        Http::assertNothingSent();
    }

    /** @test */
    public function unknown_token_returns_404(): void
    {
        $this->postJson('/webhook/no-such-token', $this->sign($this->notification()))->assertNotFound();
    }
}
