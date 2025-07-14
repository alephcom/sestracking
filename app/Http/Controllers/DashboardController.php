<?php

namespace App\Http\Controllers;

use App\Models\{Project, Email, RecipientEvent};
use App\Services\ProjectAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        
        if ($requestedProjectId === 'all' || !$requestedProjectId) {
            // Show all accessible projects
            $selectedProjectIds = $accessibleProjectIds;
        } else {
            // Validate that user has access to the requested project
            if (!in_array($requestedProjectId, $accessibleProjectIds)) {
                return response()->json(['error' => 'Unauthorized access to project'], 403);
            }
            $selectedProjectIds = [$requestedProjectId];
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

        // Get event counts from new schema
        $eventsCount = RecipientEvent::selectRaw('type, COUNT(*) as count')
            ->whereHas('recipient.email', function($query) use ($selectedProjectIds) {
                $query->whereIn('project_id', $selectedProjectIds);
            })
            ->whereBetween('event_at', [$dateFrom, $dateTo])
            ->groupBy('type')
            ->get()
            ->toArray();

        $counters = [];
        foreach ($eventsCount as $counter) {
            $counters[$counter['type']] = $counter['count'];
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

        // Get chart data from new schema
        $countersByDate = DB::table('recipient_events as re')
            ->join('email_recipients as er', 're.recipient_id', '=', 'er.id')
            ->join('emails as e', 'er.email_id', '=', 'e.id')
            ->select('re.type', DB::raw("COUNT(re.id) as count"), DB::raw("DATE_FORMAT(CONVERT_TZ(re.event_at, '+00:00', ?), '%Y-%m-%d') as daygroup"))
            ->whereIn('e.project_id', $selectedProjectIds)
            ->whereBetween('re.event_at', [$dateFrom, $dateTo])
            ->addBinding(timezoneOffsetFormatter($request->tzOffset), 'select')
            ->groupBy('daygroup', 're.type')
            ->orderBy('daygroup', 'ASC')
            ->get();

        $labels = [];
        $datasets = [];
        foreach ($countersByDate as $counter) {
            $labels[$counter->daygroup] = $counter->daygroup;

            if (empty($datasets[$counter->type])) {
                $datasets[$counter->type] = [
                    'label' => ucfirst($counter->type),
                    'data' => [],
                ];
            }

            $datasets[$counter->type]['data'][] = $counter->count;
        }

        $chartData = [
            'labels' => array_values($labels),
            'datasets' => array_values($datasets),
        ];

        // Get total emails count
        $totalEmails = Email::whereIn('project_id', $selectedProjectIds)
            ->whereBetween('sent_at', [$dateFrom, $dateTo])
            ->count();

        return response()->json([
            'counters' => $counterResults,
            'chartData' => $chartData,
            'total_emails' => $totalEmails,
        ]);
    }
}
