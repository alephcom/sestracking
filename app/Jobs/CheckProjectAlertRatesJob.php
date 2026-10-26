<?php

namespace App\Jobs;

use App\Models\Project;
use App\Notifications\BounceRateAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Previously ran inside every webhook request, recounting the project's last 24h of events
 * each time. As a unique, delayed job, a burst of events for one project triggers one check
 * that runs after the burst and sees all of it.
 */
class CheckProjectAlertRatesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Seconds to wait so events arriving close together are covered by a single check. */
    public const DELAY_SECONDS = 60;

    /**
     * Safety net: release the uniqueness lock even if a worker dies mid-job.
     */
    public int $uniqueFor = 600;

    public function __construct(public int $projectId) {}

    public function uniqueId(): string
    {
        return (string) $this->projectId;
    }

    /**
     * Check rolling 24h bounce/complaint rates and notify project admins if thresholds are exceeded.
     *
     * Rates use recipient_events in the last 24h: sends as the denominator; the bounce numerator is
     * bounce + reject + rendering_failure; complaint numerator is complaint events.
     * Alerts are suppressed for 6 hours per project per metric to avoid repeat emails.
     */
    public function handle(): void
    {
        $project = Project::find($this->projectId);
        if (! $project) {
            return;
        }

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
