<?php

namespace Tests\Feature;

use App\Jobs\CheckProjectAlertRatesJob;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AlertRateCheckCoalescingTest extends TestCase
{
    use RefreshDatabase;

    private function deliveryNotification(string $snsMessageId, string $sesMessageId): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => $snsMessageId,
            'Message' => json_encode([
                'eventType' => 'Delivery',
                'mail' => [
                    'messageId' => $sesMessageId,
                    'timestamp' => now()->toIso8601String(),
                    'source' => 'sender@example.com',
                    'destination' => ['recipient@example.com'],
                ],
                'delivery' => ['recipients' => ['recipient@example.com']],
            ]),
        ];
    }

    /** @test */
    public function burst_of_events_queues_one_delayed_check_per_project()
    {
        Queue::fake();
        $first = Project::factory()->create(['token' => 'first-token']);
        $second = Project::factory()->create(['token' => 'second-token']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/webhook/first-token', $this->deliveryNotification("sns-a-$i", "ses-a-$i"))->assertOk();
        }
        $this->postJson('/webhook/second-token', $this->deliveryNotification('sns-b-1', 'ses-b-1'))->assertOk();

        Queue::assertPushed(CheckProjectAlertRatesJob::class, 2);
        Queue::assertPushed(CheckProjectAlertRatesJob::class, fn ($job) => $job->projectId === $first->id
            && $job->delay !== null);
        Queue::assertPushed(CheckProjectAlertRatesJob::class, fn ($job) => $job->projectId === $second->id);
    }

    /** @test */
    public function check_for_deleted_project_does_nothing()
    {
        Notification::fake();

        (new CheckProjectAlertRatesJob(999999))->handle();

        Notification::assertNothingSent();
    }
}
