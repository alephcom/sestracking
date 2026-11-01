<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSesWebhook;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Sentry\captureMessage;
use function Sentry\withScope;

class SesWebhookController extends Controller
{
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

        // The job needs these to store the email; reject now rather than fail in the background
        if (! isset($ses['mail']['messageId'], $ses['mail']['source'], $ses['mail']['timestamp'])) {
            Log::warning('SES notification missing mail fields', ['message_id' => $messageId, 'payload_keys' => array_keys($ses)]);

            return response('Missing mail fields', 400);
        }

        ProcessSesWebhook::dispatch($project->id, $ses, $messageId);

        return response('OK');
    }
}
