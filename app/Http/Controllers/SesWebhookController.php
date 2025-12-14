<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\{Project, Email, EmailRecipient, RecipientEvent};

class SesWebhookController extends Controller
{
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
            
            /* 2️⃣  Throw away duplicates immediately */
            if (RecipientEvent::where('sns_message_id', $messageId)->exists()) {
                return response('Duplicate OK');
            }

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
            
            if ($sesMessageId) {
                // Create a unique ID combining messageId, eventType, and timestamp for deduplication
                $messageId = 'ses-' . md5($sesMessageId . '-' . $eventType . '-' . ($ses[$eventType]['timestamp'] ?? $timestamp));
            } else {
                // Fallback: generate from payload hash
                $messageId = 'ses-' . md5(json_encode($ses));
            }

            /* 2️⃣  Throw away duplicates immediately */
            if (RecipientEvent::where('sns_message_id', $messageId)->exists()) {
                return response('Duplicate OK');
            }
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
        $type = strtolower($ses['eventType'] ?? ($ses['notificationType'] ?? 'unknown'));

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

            /* 6️⃣  Store the event for the selected recipient */
            RecipientEvent::create([
                'recipient_id'   => $availableRecipient->id,
                'sns_message_id' => $messageId,
                'type'           => $type,
                'event_at'       => Carbon::parse(
                    $ses[$type]['timestamp'] ?? $ses['mail']['timestamp']
                ),
                'payload'        => $ses,
            ]);

            /* 8️⃣  Increment counters immediately for open/click */
            if ($type === 'open')   { $email->increment('opens'); }
            if ($type === 'click')  { $email->increment('clicks'); }
        } else {
            // Standard handling for other event types (send, delivery, bounce, etc.)
            $recipientAddresses = $ses['delivery']['recipients'] ?? $ses['mail']['destination'];

            foreach ($recipientAddresses as $address) {
                $recipient = EmailRecipient::firstOrCreate(
                    ['email_id' => $email->id, 'address' => strtolower($address)]
                );

                /* 6️⃣  Store the event once per recipient */
                RecipientEvent::create([
                    'recipient_id'   => $recipient->id,
                    'sns_message_id' => $messageId,
                    'type'           => $type,
                    'event_at'       => Carbon::parse(
                        $ses[$type]['timestamp'] ?? $ses['mail']['timestamp']
                    ),
                    'payload'        => $ses,
                ]);

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