<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Notifications\BounceRateAlert;
use App\Services\SesWebhookSuppressionDispatcher;
use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
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
    public function __construct(
        private readonly SesWebhookSuppressionDispatcher $suppressionDispatcher,
        private readonly MessageValidator $snsMessageValidator
    ) {}

    /**
     * SNS subscribe/unsubscribe links always point at the regional SNS endpoint.
     */
    private const SNS_SUBSCRIBE_URL_PATTERN = '#^https://sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?/#';

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
        if (! is_array($sns) || $sns === []) {
            if (config('sentry.dsn')) {
                withScope(function (\Sentry\State\Scope $scope) use ($request, $token): void {
                    $scope->setTag('webhook.token', $token);
                    $scope->setContext('webhook', ['content_length' => strlen($request->getContent())]);
                    captureMessage('Invalid SES webhook: malformed JSON body', \Sentry\Severity::warning());
                });
            }

            return response('Bad JSON', 400);
        }

        /* 1️⃣  Your tenant - unknown tokens never trigger any outbound request */
        $project = Project::whereToken($token)->firstOrFail();

        /* 2️⃣  Handle SNS handshake: only follow SubscribeURL from a genuine, AWS-signed message */
        if (($sns['Type'] ?? '') === 'SubscriptionConfirmation') {
            return $this->confirmSnsSubscription($sns, $token);
        }

        /* 3️⃣  Authenticate the notification itself; the URL token alone is not enough */
        if ($rejection = $this->rejectUnverifiedNotification($sns, $token)) {
            return $rejection;
        }

        if (($sns['Type'] ?? '') === 'UnsubscribeConfirmation') {
            return response('OK');
        }

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

            $ses = is_string($message) ? json_decode($message, true) : null;
            if (! is_array($ses) || $ses === []) {
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
        elseif (is_array($sns['mail'] ?? null) && (isset($sns['eventType']) || isset($sns['notificationType']))) {
            // Direct SES format: treat the payload as the SES notification directly
            $ses = $sns;

            // Generate a unique MessageId from the SES mail messageId and timestamp for deduplication
            $sesMessageId = $ses['mail']['messageId'] ?? null;
            $timestamp = $ses['mail']['timestamp'] ?? now()->toIso8601String();
            $eventType = $ses['eventType'] ?? $ses['notificationType'] ?? 'unknown';
            $eventType = is_string($eventType) ? $eventType : 'unknown';
            $payloadKey = lcfirst($eventType); // SES uses camelCase keys (e.g. deliveryDelay, renderingFailure)
            $eventTimestamp = $ses[$payloadKey]['timestamp'] ?? $timestamp;

            if (is_string($sesMessageId) && $sesMessageId !== '' && is_scalar($eventTimestamp)) {
                // Create a unique ID combining messageId, eventType, and timestamp for deduplication
                $messageId = 'ses-'.md5($sesMessageId.'-'.$eventType.'-'.$eventTimestamp);
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

        /* 5️⃣  Which event and which recipients? */
        $rawEventType = $ses['eventType'] ?? $ses['notificationType'] ?? 'unknown';
        $rawEventType = is_string($rawEventType) ? $rawEventType : 'unknown';
        $type = self::SES_EVENT_TYPE_TO_ENUM[$rawEventType] ?? self::SES_EVENT_TYPE_TO_ENUM[strtolower($rawEventType)] ?? 'unknown';
        $payloadKey = lcfirst($rawEventType); // SES payload uses camelCase (e.g. deliveryDelay, renderingFailure)

        if ($type === 'unknown') {
            // 2xx so SNS does not keep retrying event types this app does not store.
            Log::info('Ignoring unsupported SES event type', ['token' => $token, 'event_type' => $rawEventType]);

            return response('Ignored unsupported event type');
        }

        /* Reject payloads missing fields we rely on with a 400 instead of failing with a 500 */
        if ($reason = $this->invalidSesPayloadReason($ses, $type, $payloadKey)) {
            return $this->rejectInvalidPayload($token, $messageId, $reason);
        }

        $email = Email::firstOrCreate(
            ['project_id' => $project->id, 'message_id' => $ses['mail']['messageId']],
            [
                'source' => $ses['mail']['source'],
                'subject' => Str::limit($ses['mail']['commonHeaders']['subject'] ?? '', 65535, ''),
                'sent_at' => Carbon::parse($ses['mail']['timestamp']),
            ]
        );

        // Special handling for open/click events - assign to first available recipient
        if (in_array($type, ['open', 'click'])) {
            // Ensure all recipients exist first
            $recipientAddresses = $ses['delivery']['recipients'] ?? $ses['mail']['destination'];
            foreach ($recipientAddresses as $address) {
                if (! is_string($address) || trim($address) === '') {
                    continue;
                }
                EmailRecipient::firstOrCreate(
                    ['email_id' => $email->id, 'address' => strtolower(trim($address))]
                );
            }

            // An SNS retry redelivers the same MessageId. The recipient picked below depends on
            // which recipients already have this event type, so a retry would otherwise be
            // stored against the next recipient and counted twice.
            $alreadyRecorded = RecipientEvent::where('sns_message_id', $messageId)
                ->where('type', $type)
                ->whereIn('recipient_id', EmailRecipient::where('email_id', $email->id)->select('id'))
                ->exists();

            if ($alreadyRecorded) {
                return response('OK');
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

            /* 8️⃣  Increment counters once per event; SNS retries redeliver the same MessageId */
            if ($recipientEvent->wasRecentlyCreated) {
                $email->increment($type === 'open' ? 'opens' : 'clicks');
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
                if (! is_string($address)) {
                    continue;
                }

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

                /* 7️⃣  Update per-recipient status (only once thanks to UNIQUE) */
                match ($type) {
                    'delivery' => $recipient->update(['status' => 'delivered']),
                    'bounce', 'reject',
                    'rendering_failure' => $recipient->update(['status' => 'bounced']),
                    'complaint' => $recipient->update(['status' => 'complained']),
                    default => null,
                };

                if (in_array($type, ['bounce', 'complaint'], true)) {
                    $this->suppressionDispatcher->dispatchIfEligible(
                        $project,
                        $type,
                        $ses,
                        $cleanAddress,
                        $recipientEvent->wasRecentlyCreated
                    );
                }
            }
        }

        $this->checkRateThresholdsAndNotify($project);

        return response('OK');
    }

    /**
     * Verify the SNS signature and SubscribeURL host before confirming the subscription.
     * Without this, anyone could make the server fetch an arbitrary URL (SSRF).
     */
    private function confirmSnsSubscription(array $sns, string $token)
    {
        $subscribeUrl = $sns['SubscribeURL'] ?? '';

        if (! $this->hasValidSnsSignature($sns, $token)) {
            return response('Invalid SNS signature', 403);
        }

        if (! $this->isAllowedTopic($sns)) {
            Log::warning('Rejected SNS subscription confirmation: topic not allowed', [
                'token' => $token,
                'topic_arn' => $sns['TopicArn'] ?? null,
            ]);

            return response('Topic not allowed', 403);
        }

        if (! is_string($subscribeUrl) || ! preg_match(self::SNS_SUBSCRIBE_URL_PATTERN, $subscribeUrl)) {
            Log::warning('Rejected SNS subscription confirmation: unexpected SubscribeURL host', [
                'token' => $token,
            ]);

            return response('Invalid SubscribeURL', 400);
        }

        Http::timeout(10)->get($subscribeUrl);

        return response('OK');
    }

    /**
     * Fields the handler below reads without further checks. Returns a reason when one is
     * missing or malformed, or null when the payload is usable.
     */
    private function invalidSesPayloadReason(array $ses, string $type, string $payloadKey): ?string
    {
        $mail = $ses['mail'] ?? null;
        if (! is_array($mail)) {
            return 'mail object missing';
        }

        if (! is_string($mail['messageId'] ?? null) || trim($mail['messageId']) === '') {
            return 'mail.messageId missing';
        }

        if (! is_string($mail['source'] ?? null)) {
            return 'mail.source missing';
        }

        if (! $this->isParsableTimestamp($mail['timestamp'] ?? null)) {
            return 'mail.timestamp missing or invalid';
        }

        if (isset($ses[$payloadKey]['timestamp']) && ! $this->isParsableTimestamp($ses[$payloadKey]['timestamp'])) {
            return $payloadKey.'.timestamp invalid';
        }

        foreach (['mail.destination', 'delivery.recipients', 'bounce.bouncedRecipients',
            'complaint.complainedRecipients', 'deliveryDelay.delayedRecipients', 'subscription.endpoints'] as $path) {
            [$section, $field] = explode('.', $path);
            if (isset($ses[$section][$field]) && ! is_array($ses[$section][$field])) {
                return $path.' must be a list';
            }
        }

        // Opens and clicks are attributed to one of the message's recipients, so there must be one.
        if (in_array($type, ['open', 'click'], true)) {
            $addresses = $ses['delivery']['recipients'] ?? $mail['destination'] ?? [];
            $hasAddress = collect($addresses)->contains(fn ($a) => is_string($a) && trim($a) !== '');
            if (! $hasAddress) {
                return 'no recipient address for '.$type.' event';
            }
        }

        return null;
    }

    private function isParsableTimestamp(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function rejectInvalidPayload(string $token, ?string $messageId, string $reason)
    {
        Log::warning('Invalid SES webhook payload', ['token' => $token, 'message_id' => $messageId, 'reason' => $reason]);
        if (config('sentry.dsn')) {
            withScope(function (\Sentry\State\Scope $scope) use ($token, $messageId, $reason): void {
                $scope->setTag('webhook.token', $token);
                $scope->setContext('webhook', ['message_id' => $messageId, 'reason' => $reason]);
                captureMessage('Invalid SES webhook: '.$reason, \Sentry\Severity::warning());
            });
        }

        return response('Invalid SES payload: '.$reason, 400);
    }

    /**
     * Returns a rejection response when a notification fails authentication, or null to continue.
     */
    private function rejectUnverifiedNotification(array $payload, string $token)
    {
        $isSnsEnvelope = isset($payload['Type']);

        if ($isSnsEnvelope && ! $this->isAllowedTopic($payload)) {
            Log::warning('Rejected SES webhook: topic not allowed', [
                'token' => $token,
                'topic_arn' => $payload['TopicArn'] ?? null,
            ]);

            return response('Topic not allowed', 403);
        }

        if (! config('services.ses.webhook_verify_signatures', true)) {
            return null;
        }

        if (! $isSnsEnvelope) {
            if (config('services.ses.webhook_allow_direct_payloads', false)) {
                return null;
            }

            Log::warning('Rejected SES webhook: unsigned direct payload', ['token' => $token]);

            return response('Unsigned payloads are not accepted', 403);
        }

        if (! $this->hasValidSnsSignature($payload, $token)) {
            return response('Invalid SNS signature', 403);
        }

        return null;
    }

    private function hasValidSnsSignature(array $payload, string $token): bool
    {
        try {
            $this->snsMessageValidator->validate(new Message($payload));

            return true;
        } catch (InvalidSnsMessageException|\InvalidArgumentException $e) {
            Log::warning('Rejected SES webhook: invalid SNS signature', [
                'token' => $token,
                'sns_type' => $payload['Type'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function isAllowedTopic(array $payload): bool
    {
        $patterns = config('services.ses.webhook_allowed_topic_arns', []);
        if ($patterns === []) {
            return true;
        }

        $topicArn = $payload['TopicArn'] ?? null;
        if (! is_string($topicArn) || $topicArn === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $topicArn)) {
                return true;
            }
        }

        return false;
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
