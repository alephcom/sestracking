<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Notifications\BounceRateAlert;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Sentry\captureMessage;
use function Sentry\withScope;

class SesWebhookController extends Controller
{
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

    public function __invoke(Request $request, string $token)
    {
        // Debug: Log incoming payload to webhook_debug.log (if enabled)
        if (config('app.webhook_debug_log', false)) {
            $rawPayload = $request->getContent();
            $timestamp = now()->format('Y-m-d H:i:s');
            $logEntry = "[$timestamp] Incoming webhook payload: ".$rawPayload.PHP_EOL;
            file_put_contents(storage_path('logs/webhook_debug.log'), $logEntry, FILE_APPEND | LOCK_EX);
        }

        $sns = json_decode($request->getContent(), true);
        if (! $sns) {
            if (config('sentry.dsn')) {
                withScope(function (\Sentry\State\Scope $scope) use ($request, $token): void {
                    $scope->setTag('webhook.token', $token);
                    $scope->setContext('webhook', ['content_length' => strlen($request->getContent())]);
                    captureMessage('Invalid SES webhook: malformed JSON body', \Sentry\Severity::warning());
                });
            }

            return response('Bad JSON', 400);
        }

        /* 1️⃣  Handle SNS handshake */
        if (($sns['Type'] ?? '') === 'SubscriptionConfirmation') {
            Http::get($sns['SubscribeURL'] ?? '');

            return response('OK');
        }

        /* 3️⃣  Your tenant - get project early for both formats */
        $project = Project::whereToken($token)->firstOrFail();

        // Detect if this is SNS-wrapped or direct SES notification
        $messageId = null;
        $ses = null;

        // Check if this is an SNS notification (has Type and MessageId)
        if (isset($sns['Type']) && isset($sns['MessageId'])) {
            // SNS format: has MessageId at top level
            $messageId = $sns['MessageId'];

            // Note: Duplicate check is now done per recipient using firstOrCreate
            // No need to check here since we use composite unique constraint

            /* 4️⃣  Inner SES payload */
            $message = $sns['Message'] ?? null;
            if (! $message) {
                Log::warning('SNS notification missing Message field', ['message_id' => $messageId, 'sns_type' => $sns['Type'] ?? 'unknown']);
                if (config('sentry.dsn')) {
                    withScope(function (\Sentry\State\Scope $scope) use ($messageId, $sns, $token): void {
                        $scope->setTag('webhook.token', $token);
                        $scope->setContext('webhook', [
                            'message_id' => $messageId,
                            'sns_type' => $sns['Type'] ?? 'unknown',
                        ]);
                        captureMessage('Invalid SES webhook: SNS Message field missing', \Sentry\Severity::warning());
                    });
                }

                return response('Missing Message', 400);
            }

            $ses = json_decode($message, true);
            if (! $ses) {
                Log::warning('SNS Message field contains invalid JSON', ['message_id' => $messageId, 'message' => $message]);
                if (config('sentry.dsn')) {
                    withScope(function (\Sentry\State\Scope $scope) use ($messageId, $token): void {
                        $scope->setTag('webhook.token', $token);
                        $scope->setContext('webhook', ['message_id' => $messageId]);
                        captureMessage('Invalid SES webhook: SNS Message field is not valid JSON', \Sentry\Severity::warning());
                    });
                }

                return response('Invalid Message JSON', 400);
            }
        }
        // Check if this is a direct SES notification (has eventType or notificationType, and mail)
        elseif (isset($sns['mail']) && (isset($sns['eventType']) || isset($sns['notificationType']))) {
            // Direct SES format: treat the payload as the SES notification directly
            $ses = $sns;

            // Generate a unique MessageId from the SES mail messageId and timestamp for deduplication
            $sesMessageId = $ses['mail']['messageId'] ?? null;
            $timestamp = $ses['mail']['timestamp'] ?? now()->toIso8601String();
            $eventType = $ses['eventType'] ?? $ses['notificationType'] ?? 'unknown';
            $payloadKey = lcfirst($eventType); // SES uses camelCase keys (e.g. deliveryDelay, renderingFailure)

            if ($sesMessageId) {
                // Create a unique ID combining messageId, eventType, and timestamp for deduplication
                $messageId = 'ses-'.md5($sesMessageId.'-'.$eventType.'-'.($ses[$payloadKey]['timestamp'] ?? $timestamp));
            } else {
                // Fallback: generate from payload hash
                $messageId = 'ses-'.md5(json_encode($ses));
            }

            // Note: Duplicate check is now done per recipient using firstOrCreate
            // No need to check here since we use composite unique constraint
        } else {
            // Unknown format
            Log::warning('Unknown webhook payload format', ['payload_keys' => array_keys($sns), 'payload' => $sns]);
            if (config('sentry.dsn')) {
                withScope(function (\Sentry\State\Scope $scope) use ($sns, $token): void {
                    $scope->setTag('webhook.token', $token);
                    $scope->setContext('webhook', ['payload_keys' => array_keys($sns)]);
                    captureMessage('Invalid SES webhook: unknown payload format', \Sentry\Severity::warning());
                });
            }

            return response('Unknown payload format', 400);
        }

        $email = Email::firstOrCreate(
            ['project_id' => $project->id, 'message_id' => $ses['mail']['messageId']],
            [
                'source' => $ses['mail']['source'],
                'subject' => Str::limit($ses['mail']['commonHeaders']['subject'] ?? '', 65535, ''),
                'sent_at' => Carbon::parse($ses['mail']['timestamp']),
            ]
        );

        /* 5️⃣  Which event and which recipients? */
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

            /* 6️⃣  Store the event for the selected recipient (use firstOrCreate to handle duplicates) */
            RecipientEvent::firstOrCreate(
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

            /* 8️⃣  Increment counters immediately for open/click */
            if ($type === 'open') {
                $email->increment('opens');
            }
            if ($type === 'click') {
                $email->increment('clicks');
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

                /* 6️⃣  Store the event once per recipient (use firstOrCreate to handle duplicates) */
                // Determine event timestamp from event-specific payload key (camelCase) or mail
                $eventTimestamp = isset($ses[$payloadKey]['timestamp'])
                    ? Carbon::parse($ses[$payloadKey]['timestamp'])
                    : Carbon::parse($ses['mail']['timestamp'] ?? now());

                RecipientEvent::firstOrCreate(
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

                /* 7️⃣  Update per-recipient status (only once thanks to UNIQUE) */
                match ($type) {
                    'delivery' => $recipient->update(['status' => 'delivered']),
                    'bounce', 'reject',
                    'rendering_failure' => $recipient->update(['status' => 'bounced']),
                    'complaint' => $recipient->update(['status' => 'complained']),
                    default => null,
                };
            }
        }

        $this->checkRateThresholdsAndNotify($project);

        return response('OK');
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
