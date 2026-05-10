<?php

namespace App\Services;

use App\Jobs\PushSuppressedDestinationJob;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

class SesWebhookSuppressionDispatcher
{
    /**
     * Queue SES account suppression list update when the project enables auto-push
     * and the webhook event is a new bounce/complaint for this recipient.
     *
     * @param  array<string, mixed>  $ses
     */
    public function dispatchIfEligible(
        Project $project,
        string $eventType,
        array $ses,
        string $cleanAddress,
        bool $recipientEventWasRecentlyCreated
    ): void {
        if (! $recipientEventWasRecentlyCreated || ! $project->ses_suppression_auto_push_enabled) {
            return;
        }

        $reason = $this->resolveReason($project, $eventType, $ses);
        if ($reason === null) {
            return;
        }

        if (! $project->canRunSesSuppressionApi()) {
            Log::debug('SES suppression auto-push skipped: per-project AWS credentials or region not configured.', [
                'project_id' => $project->id,
                'email' => $cleanAddress,
            ]);

            return;
        }

        PushSuppressedDestinationJob::dispatch($project->id, $cleanAddress, $reason);
    }

    /**
     * @param  array<string, mixed>  $ses
     */
    private function resolveReason(Project $project, string $eventType, array $ses): ?string
    {
        if ($eventType === 'complaint') {
            return $project->ses_suppression_push_complaints ? 'COMPLAINT' : null;
        }

        if ($eventType !== 'bounce') {
            return null;
        }

        $bounceType = $ses['bounce']['bounceType'] ?? null;
        if (! is_string($bounceType) || $bounceType === '') {
            return null;
        }

        if ($bounceType === 'Undetermined') {
            return null;
        }

        if ($bounceType === 'Permanent') {
            return 'BOUNCE';
        }

        if ($bounceType === 'Transient') {
            return $project->ses_suppression_push_soft_bounces ? 'BOUNCE' : null;
        }

        return null;
    }
}
