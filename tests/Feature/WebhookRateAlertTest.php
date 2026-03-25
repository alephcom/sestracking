<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Models\User;
use App\Notifications\BounceRateAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebhookRateAlertTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function webhook_sends_bounce_rate_alert_to_project_admins_when_threshold_exceeded()
    {
        Notification::fake();

        $project = Project::factory()->create(['token' => 'rate-alert-token']);
        $admin = User::factory()->create();
        $project->users()->attach($admin, ['role' => 'admin']);

        $email = Email::factory()->create(['project_id' => $project->id, 'message_id' => 'msg-rate-1']);
        $ts = now()->subHour();

        for ($i = 0; $i < 10; $i++) {
            $recipient = EmailRecipient::factory()->create(['email_id' => $email->id]);
            RecipientEvent::factory()->create([
                'recipient_id' => $recipient->id,
                'type' => 'send',
                'event_at' => $ts,
                'sns_message_id' => 'seed-send-'.$i,
            ]);
        }
        $bounceRecipient = EmailRecipient::factory()->create(['email_id' => $email->id]);
        RecipientEvent::factory()->create([
            'recipient_id' => $bounceRecipient->id,
            'type' => 'bounce',
            'event_at' => $ts,
            'sns_message_id' => 'seed-bounce-1',
        ]);

        EmailRecipient::factory()->create(['email_id' => $email->id, 'address' => 'opener@example.com']);

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'sns-open-trigger-'.uniqid(),
            'Message' => json_encode([
                'eventType' => 'open',
                'mail' => [
                    'messageId' => 'msg-rate-1',
                    'source' => 'test@example.com',
                    'destination' => ['opener@example.com'],
                    'timestamp' => $ts->toIso8601String(),
                ],
                'open' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ]),
        ];

        $this->postJson('/webhook/rate-alert-token', $payload)->assertOk();

        Notification::assertSentTo($admin, BounceRateAlert::class, function (BounceRateAlert $n) use ($project): bool {
            return $n->metricType === 'bounce'
                && (float) $n->rate === 10.0
                && (float) $n->threshold === 5.0
                && $n->project->is($project);
        });
    }

    /** @test */
    public function webhook_suppresses_repeat_bounce_alerts_for_six_hours()
    {
        Notification::fake();

        $project = Project::factory()->create(['token' => 'rate-alert-token-2']);
        $admin = User::factory()->create();
        $project->users()->attach($admin, ['role' => 'admin']);

        $email = Email::factory()->create(['project_id' => $project->id, 'message_id' => 'msg-rate-2']);
        $ts = now()->subHour();

        for ($i = 0; $i < 10; $i++) {
            $recipient = EmailRecipient::factory()->create(['email_id' => $email->id]);
            RecipientEvent::factory()->create([
                'recipient_id' => $recipient->id,
                'type' => 'send',
                'event_at' => $ts,
                'sns_message_id' => 'seed2-send-'.$i,
            ]);
        }
        $br = EmailRecipient::factory()->create(['email_id' => $email->id]);
        RecipientEvent::factory()->create([
            'recipient_id' => $br->id,
            'type' => 'bounce',
            'event_at' => $ts,
            'sns_message_id' => 'seed2-bounce',
        ]);

        EmailRecipient::factory()->create(['email_id' => $email->id, 'address' => 'opener2@example.com']);

        $makeOpenPayload = fn (string $snsId) => [
            'Type' => 'Notification',
            'MessageId' => $snsId,
            'Message' => json_encode([
                'eventType' => 'open',
                'mail' => [
                    'messageId' => 'msg-rate-2',
                    'source' => 'test@example.com',
                    'destination' => ['opener2@example.com'],
                    'timestamp' => $ts->toIso8601String(),
                ],
                'open' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ]),
        ];

        $this->postJson('/webhook/rate-alert-token-2', $makeOpenPayload('sns-a-'.uniqid()))->assertOk();
        $this->postJson('/webhook/rate-alert-token-2', $makeOpenPayload('sns-b-'.uniqid()))->assertOk();

        Notification::assertSentToTimes($admin, BounceRateAlert::class, 1);
    }

    /** @test */
    public function bounce_alert_uses_custom_project_threshold_on_notification()
    {
        Notification::fake();

        $project = Project::factory()->create([
            'token' => 'rate-alert-token-3',
            'alert_bounce_rate' => 2.5,
        ]);
        $admin = User::factory()->create();
        $project->users()->attach($admin, ['role' => 'admin']);

        $email = Email::factory()->create(['project_id' => $project->id, 'message_id' => 'msg-rate-3']);
        $ts = now()->subHour();

        for ($i = 0; $i < 10; $i++) {
            $recipient = EmailRecipient::factory()->create(['email_id' => $email->id]);
            RecipientEvent::factory()->create([
                'recipient_id' => $recipient->id,
                'type' => 'send',
                'event_at' => $ts,
                'sns_message_id' => 'seed3-send-'.$i,
            ]);
        }
        $br = EmailRecipient::factory()->create(['email_id' => $email->id]);
        RecipientEvent::factory()->create([
            'recipient_id' => $br->id,
            'type' => 'bounce',
            'event_at' => $ts,
            'sns_message_id' => 'seed3-bounce',
        ]);

        EmailRecipient::factory()->create(['email_id' => $email->id, 'address' => 'opener3@example.com']);

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'sns-open-3-'.uniqid(),
            'Message' => json_encode([
                'eventType' => 'open',
                'mail' => [
                    'messageId' => 'msg-rate-3',
                    'source' => 'test@example.com',
                    'destination' => ['opener3@example.com'],
                    'timestamp' => $ts->toIso8601String(),
                ],
                'open' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ]),
        ];

        $this->postJson('/webhook/rate-alert-token-3', $payload)->assertOk();

        Notification::assertSentTo($admin, BounceRateAlert::class, function (BounceRateAlert $n): bool {
            return $n->metricType === 'bounce'
                && (float) $n->threshold === 2.5;
        });
    }

    /** @test */
    public function webhook_sends_complaint_rate_alert_when_threshold_exceeded()
    {
        Notification::fake();

        $project = Project::factory()->create(['token' => 'rate-alert-token-4']);
        $admin = User::factory()->create();
        $project->users()->attach($admin, ['role' => 'admin']);

        $email = Email::factory()->create(['project_id' => $project->id, 'message_id' => 'msg-rate-4']);
        $ts = now()->subHour();

        for ($i = 0; $i < 20; $i++) {
            $recipient = EmailRecipient::factory()->create(['email_id' => $email->id]);
            RecipientEvent::factory()->create([
                'recipient_id' => $recipient->id,
                'type' => 'send',
                'event_at' => $ts,
                'sns_message_id' => 'seed4-send-'.$i,
            ]);
        }
        $cr = EmailRecipient::factory()->create(['email_id' => $email->id]);
        RecipientEvent::factory()->create([
            'recipient_id' => $cr->id,
            'type' => 'complaint',
            'event_at' => $ts,
            'sns_message_id' => 'seed4-complaint',
        ]);

        EmailRecipient::factory()->create(['email_id' => $email->id, 'address' => 'opener4@example.com']);

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'sns-open-4-'.uniqid(),
            'Message' => json_encode([
                'eventType' => 'open',
                'mail' => [
                    'messageId' => 'msg-rate-4',
                    'source' => 'test@example.com',
                    'destination' => ['opener4@example.com'],
                    'timestamp' => $ts->toIso8601String(),
                ],
                'open' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ]),
        ];

        $this->postJson('/webhook/rate-alert-token-4', $payload)->assertOk();

        Notification::assertSentTo($admin, BounceRateAlert::class, function (BounceRateAlert $n) use ($project): bool {
            return $n->metricType === 'complaint'
                && (float) $n->rate === 5.0
                && (float) $n->threshold === 0.1
                && $n->project->is($project);
        });
    }

    /** @test */
    public function reject_and_rendering_failure_events_count_toward_bounce_rate_alert()
    {
        Notification::fake();

        $project = Project::factory()->create(['token' => 'rate-alert-token-5']);
        $admin = User::factory()->create();
        $project->users()->attach($admin, ['role' => 'admin']);

        $email = Email::factory()->create(['project_id' => $project->id, 'message_id' => 'msg-rate-5']);
        $ts = now()->subHour();

        for ($i = 0; $i < 10; $i++) {
            $recipient = EmailRecipient::factory()->create(['email_id' => $email->id]);
            RecipientEvent::factory()->create([
                'recipient_id' => $recipient->id,
                'type' => 'send',
                'event_at' => $ts,
                'sns_message_id' => 'seed5-send-'.$i,
            ]);
        }
        $rr = EmailRecipient::factory()->create(['email_id' => $email->id]);
        RecipientEvent::factory()->create([
            'recipient_id' => $rr->id,
            'type' => 'reject',
            'event_at' => $ts,
            'sns_message_id' => 'seed5-reject',
        ]);

        EmailRecipient::factory()->create(['email_id' => $email->id, 'address' => 'opener5@example.com']);

        $payload = [
            'Type' => 'Notification',
            'MessageId' => 'sns-open-5-'.uniqid(),
            'Message' => json_encode([
                'eventType' => 'open',
                'mail' => [
                    'messageId' => 'msg-rate-5',
                    'source' => 'test@example.com',
                    'destination' => ['opener5@example.com'],
                    'timestamp' => $ts->toIso8601String(),
                ],
                'open' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ]),
        ];

        $this->postJson('/webhook/rate-alert-token-5', $payload)->assertOk();

        Notification::assertSentTo($admin, BounceRateAlert::class, fn (BounceRateAlert $n) => $n->metricType === 'bounce');
    }
}
