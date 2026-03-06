<?php

namespace App\Http\Controllers;

use App\Models\{Project, Email, RecipientEvent};
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


    public function jsApi(Request $request, ProjectAccessService $projectService)
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
                ],
                'chartData' => [
                    'labels' => [],
                    'datasets' => [],
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

        $data = Cache::remember($cacheKey, 300, function () use ($selectedProjectIds, $dateFrom, $dateTo, $tzOffset) {
            return $this->buildDashboardData($selectedProjectIds, $dateFrom, $dateTo, $tzOffset);
        });

        return response()->json($data);
    }

    private function buildDashboardData(array $selectedProjectIds, Carbon $dateFrom, Carbon $dateTo, int $tzOffset): array
    {
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

        $counterResults = [
            'sent' => $counters['send'] ?? 0,
            'delivered' => $counters['delivery'] ?? 0,
            'opens' => $counters['open'] ?? 0,
            'clicks' => $counters['click'] ?? 0,
            'notDelivered' => $notDelivered,
        ];

        $chartData = $this->buildChartData($selectedProjectIds, $dateFrom, $dateTo, $tzOffset);

        $totalEmails = Email::whereIn('project_id', $selectedProjectIds)
            ->whereBetween('sent_at', [$dateFrom, $dateTo])
            ->count();

        return [
            'counters' => $counterResults,
            'chartData' => $chartData,
            'total_emails' => $totalEmails,
        ];
    }

    private function buildChartData(array $selectedProjectIds, Carbon $dateFrom, Carbon $dateTo, int $tzOffset): array
    {
        $grouped = [];
        $labels = [];

        if (DB::connection()->getDriverName() === 'mysql') {
            // Push grouping and timezone offset entirely into SQL — avoids loading
            // individual event rows into PHP memory
            $events = DB::table('recipient_events as re')
                ->join('email_recipients as er', 're.recipient_id', '=', 'er.id')
                ->join('emails as e', 'er.email_id', '=', 'e.id')
                ->selectRaw('re.type, DATE(DATE_ADD(re.event_at, INTERVAL ? MINUTE)) as daygroup, COUNT(*) as count', [$tzOffset])
                ->whereIn('e.project_id', $selectedProjectIds)
                ->whereBetween('re.event_at', [$dateFrom, $dateTo])
                ->groupBy('daygroup', 're.type')
                ->get();

            foreach ($events as $event) {
                $daygroup = $event->daygroup;
                $key = $daygroup . '_' . $event->type;
                $grouped[$key] = [
                    'daygroup' => $daygroup,
                    'type' => $event->type,
                    'count' => $event->count,
                ];
                $labels[$daygroup] = $daygroup;
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
                $daygroup = $eventDate->format('Y-m-d');
                $key = $daygroup . '_' . $event->type;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = ['daygroup' => $daygroup, 'type' => $event->type, 'count' => 0];
                    $labels[$daygroup] = $daygroup;
                }
                $grouped[$key]['count']++;
            }
        }

        ksort($labels);
        $labelsArray = array_values($labels);

        $datasets = [];
        foreach ($grouped as $item) {
            $type = $item['type'];
            $daygroup = $item['daygroup'];
            $count = $item['count'];

            if (empty($datasets[$type])) {
                $datasets[$type] = [
                    'label' => ucfirst($type),
                    'data' => array_fill(0, count($labelsArray), 0),
                ];
            }

            $index = array_search($daygroup, $labelsArray);
            if ($index !== false) {
                $datasets[$type]['data'][$index] = $count;
            }
        }

        return [
            'labels' => array_values($labels),
            'datasets' => array_values($datasets),
        ];
    }
}
