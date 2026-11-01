<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Notifications\BounceRateAlert;
use App\Services\DashboardCache;
use App\Services\SesWebhookSuppressionDispatcher;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Store one SES event (already unwrapped from SNS and validated by SesWebhookController).
 *
 * Processing is idempotent (firstOrCreate on the event's SNS message ID), so retries and
 * duplicate SNS deliveries do not create duplicate rows or double-count opens/clicks.
 */
class ProcessSesWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /**
     * Map SES eventType (PascalCase or lowercase) to stored enum value.
     */
    private const SES_EVENT_TYPE_TO_ENUM = [
        'Send' => 'send', 'send' => 'send',
        'RenderingFailure' => 'rendering_failure', 'renderingfailure' => 'rendering_failure', 'rendering_failure' => 'rendering_failure',
        'Reject' => 'reject', 'reject' => 'reject',
        'Delivery' => 'delivery', 'delivery' => 'delivery',
        'Bounce' => 'bounce', 'bounce' => 'bounce',
        'Complaint' => 'complaint', 'complaint' => 'complaint',
        'DeliveryDelay' => 'delivery_delay', 'deliverydelay' => 'delivery_delay', 'delivery_delay' => 'delivery_delay',
        'Subscription' => 'subscription', 'subscription' => 'subscription',
        'Open' => 'open', 'open' => 'open',
        'Click' => 'click', 'click' => 'click',
    ];

    /**
     * @param  array<string, mixed>  $ses  The SES event notification
     */
    public function __construct(
        public int $projectId,
        public array $ses,
        public string $messageId,
    ) {}

    public function handle(SesWebhookSuppressionDispatcher $suppressionDispatcher, DashboardCache $dashboardCache): void
    {
        $project = Project::find($this->projectId);
        if (! $project) {
            return;
        }

        $ses = $this->ses;
        $messageId = $this->messageId;

        $email = Email::firstOrCreate(
            ['project_id' => $project->id, 'message_id' => $ses['mail']['messageId']],
            [
                'source' => $ses['mail']['source'],
                'subject' => Str::limit($ses['mail']['commonHeaders']['subject'] ?? '', 65535, ''),
                'sent_at' => Carbon::parse($ses['mail']['timestamp']),
            ]
        );

        /* Which event and which recipients? */
        $rawEventType = $ses['eventType'] ?? $ses['notificationType'] ?? 'unknown';
        $type = self::SES_EVENT_TYPE_TO_ENUM[$rawEventType] ?? self::SES_EVENT_TYPE_TO_ENUM[strtolower($rawEventType)] ?? 'unknown';
        $payloadKey = lcfirst($rawEventType); // SES payload uses camelCase (e.g. deliveryDelay, renderingFailure)

        // Special handling for open/click events - assign to first available recipient
        if (in_array($type, ['open', 'click'])) {
            // Ensure all recipients exist first
            $recipientAddresses = $ses['delivery']['recipients'] ?? $ses['mail']['destination'];
            foreach ($recipientAddresses as $address) {
                EmailRecipient::firstOrCreate(
                    ['email_id' => $email->id, 'address' => strtolower($address)]
                );
            }

            // A retry or duplicate SNS delivery of this event: it is already stored and counted
            if (RecipientEvent::where('sns_message_id', $messageId)->where('type', $type)->exists()) {
                $this->checkRateThresholdsAndNotify($project);

                return;
            }

            // Find first recipient who doesn't already have this event type
            $availableRecipient = EmailRecipient::where('email_id', $email->id)
                ->whereNotExists(function ($query) use ($type) {
                    $query->select('id')
                        ->from('recipient_events')
                        ->whereColumn('recipient_events.recipient_id', 'email_recipients.id')
                        ->where('recipient_events.type', $type);
                })
                ->first();

            // If no available recipient (all have this event), use the first one
            if (! $availableRecipient) {
                $availableRecipient = EmailRecipient::where('email_id', $email->id)->first();
            }

            /* Store the event for the selected recipient (use firstOrCreate to handle duplicates) */
            $recipientEvent = RecipientEvent::firstOrCreate(
                [
                    'sns_message_id' => $messageId,
                    'recipient_id' => $availableRecipient->id,
                    'type' => $type,
                ],
                [
                    'event_at' => Carbon::parse(
                        $ses[$payloadKey]['timestamp'] ?? $ses['mail']['timestamp']
                    ),
                    'payload' => $ses,
                ]
            );

            /* Increment counters for open/click (only for a newly stored event) */
            if ($recipientEvent->wasRecentlyCreated) {
                if ($type === 'open') {
                    $email->increment('opens');
                }
                if ($type === 'click') {
                    $email->increment('clicks');
                }
            }
        } else {
            // Standard handling for other event types (send, delivery, bounce, etc.)
            // For bounce events, recipients are in bounce.bouncedRecipients[].emailAddress
            if ($type === 'bounce' && isset($ses['bounce']['bouncedRecipients'])) {
                $recipientAddresses = array_column($ses['bounce']['bouncedRecipients'], 'emailAddress');
            } elseif ($type === 'complaint' && isset($ses['complaint']['complainedRecipients'])) {
                $recipientAddresses = array_column($ses['complaint']['complainedRecipients'], 'emailAddress');
            } elseif ($type === 'subscription') {
                $recipientAddresses = $this->subscriptionRecipientAddresses($ses);
            } elseif ($type === 'delivery_delay' && isset($ses['deliveryDelay']['delayedRecipients'])) {
                $recipientAddresses = array_column($ses['deliveryDelay']['delayedRecipients'], 'emailAddress');
            } else {
                // For other event types, use delivery.recipients or mail.destination
                $recipientAddresses = $ses['delivery']['recipients'] ?? $ses['mail']['destination'] ?? [];
            }

            foreach ($recipientAddresses as $address) {
                // Clean up the address (remove whitespace, convert to lowercase)
                $cleanAddress = strtolower(trim($address));
                if (empty($cleanAddress)) {
                    continue;
                }

                $recipient = EmailRecipient::firstOrCreate(
                    ['email_id' => $email->id, 'address' => $cleanAddress]
                );

                /* Store the event once per recipient (use firstOrCreate to handle duplicates) */
                // Determine event timestamp from event-specific payload key (camelCase) or mail
                $eventTimestamp = isset($ses[$payloadKey]['timestamp'])
                    ? Carbon::parse($ses[$payloadKey]['timestamp'])
                    : Carbon::parse($ses['mail']['timestamp'] ?? now());

                $recipientEvent = RecipientEvent::firstOrCreate(
                    [
                        'sns_message_id' => $messageId,
                        'recipient_id' => $recipient->id,
                        'type' => $type,
                    ],
                    [
                        'event_at' => $eventTimestamp,
                        'payload' => $ses,
                    ]
                );

                /* Update per-recipient status (only once thanks to UNIQUE) */
                match ($type) {
                    'delivery' => $recipient->update(['status' => 'delivered']),
                    'bounce', 'reject',
                    'rendering_failure' => $recipient->update(['status' => 'bounced']),
                    'complaint' => $recipient->update(['status' => 'complained']),
                    default => null,
                };

                if (in_array($type, ['bounce', 'complaint'], true)) {
                    $suppressionDispatcher->dispatchIfEligible(
                        $project,
                        $type,
                        $ses,
                        $cleanAddress,
                        $recipientEvent->wasRecentlyCreated
                    );
                }
            }
        }

        // New data for this project: let the dashboard pick it up on its next load
        $dashboardCache->flushProject($project->id);

        $this->checkRateThresholdsAndNotify($project);
    }

    /**
     * Recipient addresses for SES Subscription events (List-Unsubscribe / contact-list preferences).
     *
     * Uses subscription.endpoints when present; otherwise mail.destination / delivery.recipients
     * per AWS-published Subscription records.
     */
    private function subscriptionRecipientAddresses(array $ses): array
    {
        $endpoints = $ses['subscription']['endpoints'] ?? null;
        if (is_array($endpoints) && $endpoints !== []) {
            $out = [];
            foreach ($endpoints as $ep) {
                if (is_string($ep)) {
                    $out[] = $ep;

                    continue;
                }
                if (is_array($ep)) {
                    foreach (['emailAddress', 'address', 'email'] as $key) {
                        if (! empty($ep[$key])) {
                            $out[] = $ep[$key];
                            break;
                        }
                    }
                }
            }

            return $out;
        }

        return $ses['delivery']['recipients'] ?? $ses['mail']['destination'] ?? [];
    }

    /**
     * Check rolling 24h bounce/complaint rates and notify project admins if thresholds are exceeded.
     *
     * Rates use recipient_events in the last 24h: sends as the denominator; the bounce numerator is
     * bounce + reject + rendering_failure; complaint numerator is complaint events.
     * Alerts are suppressed for 6 hours per project per metric to avoid repeat emails.
     */
    private function checkRateThresholdsAndNotify(Project $project): void
    {
        $since = now()->subDay();
        $eventsCount = DB::table('recipient_events as re')
            ->join('email_recipients as er', 're.recipient_id', '=', 'er.id')
            ->join('emails as e', 'er.email_id', '=', 'e.id')
            ->where('e.project_id', $project->id)
            ->where('re.event_at', '>=', $since)
            ->selectRaw('re.type, COUNT(*) as count')
            ->groupBy('re.type')
            ->get();

        $counters = [];
        foreach ($eventsCount as $row) {
            $counters[$row->type] = (int) $row->count;
        }
        $sent = $counters['send'] ?? 0;
        if ($sent === 0) {
            return;
        }

        // Treat reject and rendering_failure like bounces for alerting (delivery failures / content issues).
        $bounceCount = ($counters['bounce'] ?? 0)
            + ($counters['reject'] ?? 0)
            + ($counters['rendering_failure'] ?? 0);
        $complaintCount = $counters['complaint'] ?? 0;
        $bounceRate = round($bounceCount / $sent * 100, 2);
        $complaintRate = round($complaintCount / $sent * 100, 2);

        $bounceThreshold = (float) ($project->alert_bounce_rate ?? 5.0);
        $complaintThreshold = (float) ($project->alert_complaint_rate ?? 0.1);

        $bounceCacheKey = 'alert-sent:'.$project->id.':bounce';
        $complaintCacheKey = 'alert-sent:'.$project->id.':complaint';

        if ($bounceRate > $bounceThreshold && ! Cache::get($bounceCacheKey)) {
            $admins = $project->admins()->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new BounceRateAlert($project, 'bounce', $bounceRate, $bounceThreshold));
                Cache::put($bounceCacheKey, true, now()->addHours(6));
            }
        }

        if ($complaintRate > $complaintThreshold && ! Cache::get($complaintCacheKey)) {
            $admins = $project->admins()->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new BounceRateAlert($project, 'complaint', $complaintRate, $complaintThreshold));
                Cache::put($complaintCacheKey, true, now()->addHours(6));
            }
        }
    }
}
