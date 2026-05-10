<?php

namespace Tests\Feature;

use App\Jobs\PushSuppressedDestinationJob;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SesSuppressionWebhookDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create([
            'token' => 'ses-suppress-token',
            'ses_suppression_auto_push_enabled' => true,
            'ses_suppression_push_complaints' => true,
            'ses_suppression_push_soft_bounces' => false,
            'ses_aws_access_key_id' => 'AKIATESTSUPPRESS1',
            'ses_aws_secret_access_key' => 'test-secret-key-for-suppression-1',
            'ses_aws_default_region' => 'us-east-1',
        ]);
    }

    /** @test */
    public function webhook_dispatches_suppression_job_for_hard_bounce_when_auto_push_enabled(): void
    {
        Bus::fake();

        $payload = $this->wrapSns('bounce', [
            'mail' => [
                'messageId' => 'bounce-hard-1',
                'source' => 'sender@example.com',
                'destination' => ['hard@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => ['subject' => 'S'],
            ],
            'bounce' => [
                'timestamp' => '2025-01-01T10:01:00.000Z',
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
                    ['emailAddress' => 'hard@example.com'],
                ],
            ],
        ]);

        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();

        Bus::assertDispatched(PushSuppressedDestinationJob::class, function (PushSuppressedDestinationJob $job): bool {
            return $job->projectId === $this->project->id
                && $job->email === 'hard@example.com'
                && $job->reason === 'BOUNCE';
        });
    }

    /** @test */
    public function webhook_does_not_dispatch_suppression_job_when_project_aws_keys_missing(): void
    {
        $this->project->update([
            'ses_aws_access_key_id' => null,
            'ses_aws_secret_access_key' => null,
            'ses_aws_default_region' => null,
        ]);
        Bus::fake();

        $payload = $this->wrapSns('bounce', [
            'mail' => [
                'messageId' => 'bounce-no-keys',
                'source' => 'sender@example.com',
                'destination' => ['nokeys@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => ['subject' => 'S'],
            ],
            'bounce' => [
                'timestamp' => '2025-01-01T10:01:00.000Z',
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
                    ['emailAddress' => 'nokeys@example.com'],
                ],
            ],
        ]);

        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();

        Bus::assertNothingDispatched();
    }

    /** @test */
    public function webhook_does_not_dispatch_when_auto_push_disabled(): void
    {
        $this->project->update(['ses_suppression_auto_push_enabled' => false]);
        Bus::fake();

        $payload = $this->wrapSns('bounce', [
            'mail' => [
                'messageId' => 'bounce-off-1',
                'source' => 'sender@example.com',
                'destination' => ['off@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => ['subject' => 'S'],
            ],
            'bounce' => [
                'timestamp' => '2025-01-01T10:01:00.000Z',
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
                    ['emailAddress' => 'off@example.com'],
                ],
            ],
        ]);

        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();

        Bus::assertNothingDispatched();
    }

    /** @test */
    public function webhook_does_not_dispatch_for_transient_bounce_when_soft_bounces_disabled(): void
    {
        Bus::fake();

        $payload = $this->wrapSns('bounce', [
            'mail' => [
                'messageId' => 'bounce-soft-1',
                'source' => 'sender@example.com',
                'destination' => ['soft@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => ['subject' => 'S'],
            ],
            'bounce' => [
                'timestamp' => '2025-01-01T10:01:00.000Z',
                'bounceType' => 'Transient',
                'bouncedRecipients' => [
                    ['emailAddress' => 'soft@example.com'],
                ],
            ],
        ]);

        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();

        Bus::assertNothingDispatched();
    }

    /** @test */
    public function webhook_dispatches_for_transient_bounce_when_soft_bounces_enabled(): void
    {
        $this->project->update(['ses_suppression_push_soft_bounces' => true]);
        Bus::fake();

        $payload = $this->wrapSns('bounce', [
            'mail' => [
                'messageId' => 'bounce-soft-2',
                'source' => 'sender@example.com',
                'destination' => ['soft2@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => ['subject' => 'S'],
            ],
            'bounce' => [
                'timestamp' => '2025-01-01T10:01:00.000Z',
                'bounceType' => 'Transient',
                'bouncedRecipients' => [
                    ['emailAddress' => 'soft2@example.com'],
                ],
            ],
        ]);

        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();

        Bus::assertDispatched(PushSuppressedDestinationJob::class, function (PushSuppressedDestinationJob $job): bool {
            return $job->email === 'soft2@example.com' && $job->reason === 'BOUNCE';
        });
    }

    /** @test */
    public function webhook_dispatches_complaint_job_when_push_complaints_enabled(): void
    {
        Bus::fake();

        $payload = $this->wrapSns('complaint', [
            'mail' => [
                'messageId' => 'complaint-1',
                'source' => 'sender@example.com',
                'destination' => ['bad@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => ['subject' => 'S'],
            ],
            'complaint' => [
                'timestamp' => '2025-01-01T10:03:00.000Z',
                'complainedRecipients' => [
                    ['emailAddress' => 'bad@example.com'],
                ],
            ],
        ]);

        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();

        Bus::assertDispatched(PushSuppressedDestinationJob::class, function (PushSuppressedDestinationJob $job): bool {
            return $job->email === 'bad@example.com' && $job->reason === 'COMPLAINT';
        });
    }

    /** @test */
    public function webhook_does_not_dispatch_complaint_when_push_complaints_disabled(): void
    {
        $this->project->update(['ses_suppression_push_complaints' => false]);
        Bus::fake();

        $payload = $this->wrapSns('complaint', [
            'mail' => [
                'messageId' => 'complaint-off',
                'source' => 'sender@example.com',
                'destination' => ['x@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => ['subject' => 'S'],
            ],
            'complaint' => [
                'timestamp' => '2025-01-01T10:03:00.000Z',
                'complainedRecipients' => [
                    ['emailAddress' => 'x@example.com'],
                ],
            ],
        ]);

        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();

        Bus::assertNothingDispatched();
    }

    /** @test */
    public function duplicate_webhook_does_not_dispatch_second_suppression_job(): void
    {
        Bus::fake();

        $payload = $this->wrapSns('bounce', [
            'mail' => [
                'messageId' => 'bounce-dedupe',
                'source' => 'sender@example.com',
                'destination' => ['dedupe@example.com'],
                'timestamp' => '2025-01-01T10:00:00.000Z',
                'commonHeaders' => ['subject' => 'S'],
            ],
            'bounce' => [
                'timestamp' => '2025-01-01T10:01:00.000Z',
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
                    ['emailAddress' => 'dedupe@example.com'],
                ],
            ],
        ], 'fixed-sns-msg-id');

        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();
        $this->postJson('/webhook/ses-suppress-token', $payload)->assertOk();

        Bus::assertDispatchedTimes(PushSuppressedDestinationJob::class, 1);
    }

    /**
     * @param  array<string, mixed>  $sesData
     */
    private function wrapSns(string $eventType, array $sesData, ?string $snsMessageId = null): array
    {
        $snsMessageId = $snsMessageId ?? 'sns-'.uniqid('', true);

        return [
            'Type' => 'Notification',
            'MessageId' => $snsMessageId,
            'Message' => json_encode(array_merge([
                'eventType' => $eventType,
            ], $sesData)),
        ];
    }
}
