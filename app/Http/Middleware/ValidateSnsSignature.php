<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Services\SnsMessageValidator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\captureMessage;
use function Sentry\withScope;

/**
 * Rejects webhook requests that are not signed by AWS SNS, unless the project
 * has opted in to accepting unsigned requests (e.g. direct SES payloads).
 */
class ValidateSnsSignature
{
    public function __construct(
        private readonly SnsMessageValidator $validator
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');
        $project = Project::whereToken($token)->first();
        if (! $project) {
            abort(404);
        }

        $message = json_decode($request->getContent(), true);

        // The bypass covers unsigned event payloads only. Subscription confirmations make the app
        // visit their SubscribeURL, so they must always be signed (they only ever come from SNS).
        $isConfirmation = is_array($message)
            && in_array($message['Type'] ?? null, ['SubscriptionConfirmation', 'UnsubscribeConfirmation'], true);
        if ($project->allow_unsigned_webhooks && ! $isConfirmation) {
            return $next($request);
        }

        if (is_array($message) && $this->validator->isValid($message)) {
            return $next($request);
        }

        Log::warning('Rejected webhook request without a valid SNS signature', [
            'project_id' => $project->id,
            'sns_type' => is_array($message) ? ($message['Type'] ?? null) : null,
            'ip' => $request->ip(),
        ]);
        if (config('sentry.dsn')) {
            withScope(function (\Sentry\State\Scope $scope) use ($project): void {
                $scope->setTag('webhook.project_id', (string) $project->id);
                captureMessage('Rejected SES webhook: invalid or missing SNS signature', \Sentry\Severity::warning());
            });
        }

        return response('Invalid SNS signature', 403);
    }
}
