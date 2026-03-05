<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\{Project, Email, EmailRecipient, RecipientEvent};

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
            $logEntry = "[$timestamp] Incoming webhook payload: " . $rawPayload . PHP_EOL;
            file_put_contents(storage_path('logs/webhook_debug.log'), $logEntry, FILE_APPEND | LOCK_EX);
        }
        
        $sns = json_decode($request->getContent(), true);
        if (! $sns) {
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
            if (!$message) {
                Log::warning('SNS notification missing Message field', ['message_id' => $messageId, 'sns_type' => $sns['Type'] ?? 'unknown']);
                return response('Missing Message', 400);
            }

            $ses = json_decode($message, true);
            if (!$ses) {
                Log::warning('SNS Message field contains invalid JSON', ['message_id' => $messageId, 'message' => $message]);
                return response('Invalid Message JSON', 400);
            }
        } 
        // Check if this is a direct SES notification (has eventType and mail)
        elseif (isset($sns['eventType']) && isset($sns['mail'])) {
            // Direct SES format: treat the payload as the SES notification directly
            $ses = $sns;
            
            // Generate a unique MessageId from the SES mail messageId and timestamp for deduplication
            $sesMessageId = $ses['mail']['messageId'] ?? null;
            $timestamp = $ses['mail']['timestamp'] ?? now()->toIso8601String();
            $eventType = $ses['eventType'] ?? 'unknown';
            $payloadKey = lcfirst($eventType); // SES uses camelCase keys (e.g. deliveryDelay, renderingFailure)
            
            if ($sesMessageId) {
                // Create a unique ID combining messageId, eventType, and timestamp for deduplication
                $messageId = 'ses-' . md5($sesMessageId . '-' . $eventType . '-' . ($ses[$payloadKey]['timestamp'] ?? $timestamp));
            } else {
                // Fallback: generate from payload hash
                $messageId = 'ses-' . md5(json_encode($ses));
            }

            // Note: Duplicate check is now done per recipient using firstOrCreate
            // No need to check here since we use composite unique constraint
        } else {
            // Unknown format
            Log::warning('Unknown webhook payload format', ['payload_keys' => array_keys($sns), 'payload' => $sns]);
            return response('Unknown payload format', 400);
        }

        $email = Email::firstOrCreate(
            ['project_id' => $project->id, 'message_id' => $ses['mail']['messageId']],
            [
                'source'   => $ses['mail']['source'],
                'subject'  => $ses['mail']['commonHeaders']['subject'] ?? '',
                'sent_at'  => Carbon::parse($ses['mail']['timestamp']),
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
            if (!$availableRecipient) {
                $availableRecipient = EmailRecipient::where('email_id', $email->id)->first();
            }

            /* 6️⃣  Store the event for the selected recipient (use firstOrCreate to handle duplicates) */
            RecipientEvent::firstOrCreate(
                [
                    'sns_message_id' => $messageId,
                    'recipient_id'   => $availableRecipient->id,
                    'type'           => $type,
                ],
                [
                    'event_at'       => Carbon::parse(
                        $ses[$payloadKey]['timestamp'] ?? $ses['mail']['timestamp']
                    ),
                    'payload'        => $ses,
                ]
            );

            /* 8️⃣  Increment counters immediately for open/click */
            if ($type === 'open')   { $email->increment('opens'); }
            if ($type === 'click')  { $email->increment('clicks'); }
        } else {
            // Standard handling for other event types (send, delivery, bounce, etc.)
            // For bounce events, recipients are in bounce.bouncedRecipients[].emailAddress
            if ($type === 'bounce' && isset($ses['bounce']['bouncedRecipients'])) {
                $recipientAddresses = array_column($ses['bounce']['bouncedRecipients'], 'emailAddress');
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
                        'recipient_id'   => $recipient->id,
                        'type'           => $type,
                    ],
                    [
                        'event_at'       => $eventTimestamp,
                        'payload'        => $ses,
                    ]
                );

                /* 7️⃣  Update per-recipient status (only once thanks to UNIQUE) */
                match ($type) {
                    'delivery'        => $recipient->update(['status' => 'delivered']),
                    'bounce', 'reject',
                    'rendering_failure' => $recipient->update(['status' => 'bounced']),
                    'complaint'       => $recipient->update(['status' => 'complained']),
                    default           => null,
                };
            }
        }

        return response('OK');
    }
}