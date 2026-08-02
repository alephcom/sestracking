<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Services\DashboardChartAggregator;
use App\Services\ProjectAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(ProjectAccessService $projectService)
    {
        $accessibleProjects = $projectService->getAccessibleProjects(auth()->user());
        
        if ($accessibleProjects->isEmpty()) {
            session()->flash('alert', 'No projects available');
        }

        return view('dashboard.index', compact('accessibleProjects'));
    }


    public function jsApi(Request $request, ProjectAccessService $projectService, DashboardChartAggregator $chartAggregator)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);
        
        // SECURITY: Validate project access - never trust user input
        $requestedProjectId = $request->get('projectId');
        
        if ($requestedProjectId === 'all' || !$requestedProjectId || empty($requestedProjectId)) {
            $selectedProjectIds = $accessibleProjectIds;
        } else {
            if (is_string($requestedProjectId) && strpos($requestedProjectId, ',') !== false) {
                $requestedIds = array_map('trim', explode(',', $requestedProjectId));
            } else {
                $requestedIds = [$requestedProjectId];
            }
            
            $selectedProjectIds = array_intersect(
                array_map('intval', $requestedIds),
                $accessibleProjectIds
            );
            
            if (empty($selectedProjectIds)) {
                return response()->json(['error' => 'No accessible projects selected'], 403);
            }
        }

        if (empty($selectedProjectIds)) {
            return response()->json([
                'counters' => [
                    'sent' => 0,
                    'delivered' => 0,
                    'opens' => 0,
                    'clicks' => 0,
                    'notDelivered' => 0,
                    'bounce' => 0,
                    'complaint' => 0,
                ],
                'bounceRate' => 0,
                'complaintRate' => 0,
                'chartData' => [
                    'labels' => [],
                    'datasets' => [],
                    'granularity' => '1d',
                ],
            ]);
        }

        try {
            $dateFrom = Carbon::parse($request->get('dateFrom'));
            $dateTo = Carbon::parse($request->get('dateTo'));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Wrong range date!'], 400);
        }

        $tzOffset = (int)($request->tzOffset ?? 0);

        $cacheKey = 'dashboard_api_' . md5(
            implode(',', $selectedProjectIds) .
            $dateFrom->toDateTimeString() .
            $dateTo->toDateTimeString() .
            $tzOffset
        );

        $data = Cache::remember($cacheKey, 300, function () use ($selectedProjectIds, $dateFrom, $dateTo, $tzOffset, $chartAggregator) {
            return $this->buildDashboardData($selectedProjectIds, $dateFrom, $dateTo, $tzOffset, $chartAggregator);
        });

        return response()->json($data);
    }

    private function buildDashboardData(
        array $selectedProjectIds,
        Carbon $dateFrom,
        Carbon $dateTo,
        int $tzOffset,
        DashboardChartAggregator $chartAggregator
    ): array {
        // Direct JOIN avoids nested EXISTS subqueries from whereHas
        $eventsCount = DB::table('recipient_events as re')
            ->join('email_recipients as er', 're.recipient_id', '=', 'er.id')
            ->join('emails as e', 'er.email_id', '=', 'e.id')
            ->selectRaw('re.type, COUNT(*) as count')
            ->whereIn('e.project_id', $selectedProjectIds)
            ->whereBetween('re.event_at', [$dateFrom, $dateTo])
            ->groupBy('re.type')
            ->get();

        $counters = [];
        foreach ($eventsCount as $counter) {
            $counters[$counter->type] = $counter->count;
        }

        $notDelivered = ($counters['rendering_failure'] ?? 0)
            + ($counters['complaint'] ?? 0)
            + ($counters['bounce'] ?? 0)
            + ($counters['reject'] ?? 0);

        $sent = $counters['send'] ?? 0;
        $bounceCount = $counters['bounce'] ?? 0;
        $complaintCount = $counters['complaint'] ?? 0;

        $counterResults = [
            'sent' => $sent,
            'delivered' => $counters['delivery'] ?? 0,
            'opens' => $counters['open'] ?? 0,
            'clicks' => $counters['click'] ?? 0,
            'notDelivered' => $notDelivered,
            'bounce' => $bounceCount,
            'complaint' => $complaintCount,
        ];

        $bounceRate = $sent > 0 ? round($bounceCount / $sent * 100, 2) : 0;
        $complaintRate = $sent > 0 ? round($complaintCount / $sent * 100, 2) : 0;

        $chartData = $this->buildChartData($selectedProjectIds, $dateFrom, $dateTo, $tzOffset, $chartAggregator);

        $totalEmails = Email::whereIn('project_id', $selectedProjectIds)
            ->whereBetween('sent_at', [$dateFrom, $dateTo])
            ->count();

        return [
            'counters' => $counterResults,
            'bounceRate' => $bounceRate,
            'complaintRate' => $complaintRate,
            'chartData' => $chartData,
            'total_emails' => $totalEmails,
        ];
    }

    private function buildChartData(
        array $selectedProjectIds,
        Carbon $dateFrom,
        Carbon $dateTo,
        int $tzOffset,
        DashboardChartAggregator $chartAggregator
    ): array {
        $minutes = $chartAggregator->bucketMinutes($dateFrom, $dateTo);
        $labelsArray = $chartAggregator->buildLabels($dateFrom, $dateTo, $tzOffset, $minutes);
        $labelIndex = array_flip($labelsArray);
        $grouped = [];

        if (DB::connection()->getDriverName() === 'mysql') {
            [$select, $bindings] = $chartAggregator->mysqlBucketSelect($tzOffset, $minutes);

            $events = DB::table('recipient_events as re')
                ->join('email_recipients as er', 're.recipient_id', '=', 'er.id')
                ->join('emails as e', 'er.email_id', '=', 'e.id')
                ->selectRaw($select, $bindings)
                ->whereIn('e.project_id', $selectedProjectIds)
                ->whereBetween('re.event_at', [$dateFrom, $dateTo])
                ->groupBy('bucket', 're.type')
                ->get();

            foreach ($events as $event) {
                $bucket = $chartAggregator->bucketKey(Carbon::parse($event->bucket), $minutes);
                $grouped[$bucket . '_' . $event->type] = [
                    'bucket' => $bucket,
                    'type' => $event->type,
                    'count' => (int) $event->count,
                ];
            }
        } else {
            // SQLite fallback: fetch minimal columns and group in PHP
            $events = DB::table('recipient_events as re')
                ->join('email_recipients as er', 're.recipient_id', '=', 'er.id')
                ->join('emails as e', 'er.email_id', '=', 'e.id')
                ->select('re.type', 're.event_at')
                ->whereIn('e.project_id', $selectedProjectIds)
                ->whereBetween('re.event_at', [$dateFrom, $dateTo])
                ->get();

            foreach ($events as $event) {
                $eventDate = Carbon::parse($event->event_at);
                if ($tzOffset !== 0) {
                    $eventDate->addMinutes($tzOffset);
                }
                $bucket = $chartAggregator->bucketKey($eventDate, $minutes);
                $key = $bucket . '_' . $event->type;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = ['bucket' => $bucket, 'type' => $event->type, 'count' => 0];
                }
                $grouped[$key]['count']++;
            }
        }

        $datasets = [];
        foreach ($grouped as $item) {
            $type = $item['type'];
            $bucket = $item['bucket'];
            $count = $item['count'];

            if (empty($datasets[$type])) {
                $datasets[$type] = [
                    'label' => ucfirst($type),
                    'data' => array_fill(0, count($labelsArray), 0),
                ];
            }

            if (isset($labelIndex[$bucket])) {
                $datasets[$type]['data'][$labelIndex[$bucket]] = $count;
            }
        }

        return [
            'labels' => $labelsArray,
            'datasets' => array_values($datasets),
            'granularity' => $chartAggregator->granularity($minutes),
        ];
    }
}
