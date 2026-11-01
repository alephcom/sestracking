<?php

namespace Tests\Feature;

use App\Jobs\ProcessSesWebhook;
use App\Models\Email;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Services\DashboardCache;
use App\Services\SesWebhookSuppressionDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookQueueTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['token' => 'queue-token']);
    }

    private function snsPayload(string $eventType, array $extra = [], string $snsMessageId = 'sns-queue-1'): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => $snsMessageId,
            'Message' => json_encode(array_merge([
                'eventType' => $eventType,
                'mail' => [
                    'messageId' => 'ses-queue-msg',
                    'source' => 'sender@example.com',
                    'destination' => ['reader@example.com'],
                    'timestamp' => '2025-01-01T10:00:00.000Z',
                    'commonHeaders' => ['subject' => 'Queued'],
                ],
            ], $extra)),
        ];
    }

    /** @test */
    public function webhook_queues_processing_and_returns_immediately(): void
    {
        Queue::fake();

        $this->postJson('/webhook/queue-token', $this->snsPayload('Delivery'))->assertOk();

        Queue::assertPushed(ProcessSesWebhook::class, function (ProcessSesWebhook $job): bool {
            return $job->projectId === $this->project->id
                && $job->messageId === 'sns-queue-1'
                && $job->ses['mail']['messageId'] === 'ses-queue-msg';
        });
        $this->assertSame(0, Email::count());
    }

    /** @test */
    public function webhook_rejects_notification_without_mail_fields_and_queues_nothing(): void
    {
        Queue::fake();

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'sns-no-mail',
            'Message' => json_encode(['eventType' => 'Delivery', 'mail' => ['source' => 'x@example.com']]),
        ];

        $this->postJson('/webhook/queue-token', $payload)->assertStatus(400);

        Queue::assertNothingPushed();
    }

    /** @test */
    public function webhook_with_unknown_token_is_rejected_and_queues_nothing(): void
    {
        Queue::fake();

        $this->postJson('/webhook/not-a-token', $this->snsPayload('Delivery'))->assertNotFound();

        Queue::assertNothingPushed();
    }

    /** @test */
    public function processing_the_same_open_event_twice_counts_it_once(): void
    {
        $payload = $this->snsPayload('Open', ['open' => ['timestamp' => '2025-01-01T10:05:00.000Z']], 'sns-open-dup');

        $this->postJson('/webhook/queue-token', $payload)->assertOk();
        $this->postJson('/webhook/queue-token', $payload)->assertOk();

        $this->assertSame(1, Email::first()->opens);
        $this->assertSame(1, RecipientEvent::where('type', 'open')->count());
    }

    /** @test */
    public function job_does_nothing_when_the_project_has_been_deleted(): void
    {
        $job = new ProcessSesWebhook(999999, json_decode($this->snsPayload('Delivery')['Message'], true), 'sns-gone');

        $job->handle(app(SesWebhookSuppressionDispatcher::class), app(DashboardCache::class));

        $this->assertSame(0, Email::count());
    }
}
