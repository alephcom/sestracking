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
        
        if ($requestedProjectId === 'all' || !$requestedProjectId || empty($requestedProjectId)) {
            // Show all accessible projects
            $selectedProjectIds = $accessibleProjectIds;
        } else {
            // Handle comma-separated project IDs for multi-select
            if (is_string($requestedProjectId) && strpos($requestedProjectId, ',') !== false) {
                $requestedIds = array_map('trim', explode(',', $requestedProjectId));
            } else {
                $requestedIds = [$requestedProjectId];
            }
            
            // Filter to only include accessible project IDs
            $selectedProjectIds = array_intersect(
                array_map('intval', $requestedIds),
                $accessibleProjectIds
            );
            
            // If no valid projects selected, return empty result
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
        // Use database-agnostic approach by grouping in PHP to support both MySQL and SQLite
        $events = DB::table('recipient_events as re')
            ->join('email_recipients as er', 're.recipient_id', '=', 'er.id')
            ->join('emails as e', 'er.email_id', '=', 'e.id')
            ->select('re.type', 're.event_at')
            ->whereIn('e.project_id', $selectedProjectIds)
            ->whereBetween('re.event_at', [$dateFrom, $dateTo])
            ->get();

        // Group events by date (in user's timezone) and type
        $tzOffset = (int)($request->tzOffset ?? 0); // minutes offset - cast to int
        $grouped = [];
        $labels = [];
        
        foreach ($events as $event) {
            // Convert timestamp to user's timezone
            $eventDate = Carbon::parse($event->event_at);
            if ($tzOffset != 0) {
                $eventDate->addMinutes($tzOffset);
            }
            $daygroup = $eventDate->format('Y-m-d');
            
            $key = $daygroup . '_' . $event->type;
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'daygroup' => $daygroup,
                    'type' => $event->type,
                    'count' => 0,
                ];
                $labels[$daygroup] = $daygroup;
            }
            
            $grouped[$key]['count']++;
        }
        
        // Sort by date
        ksort($labels);
        
        // Build datasets grouped by type
        $datasets = [];
        $labelsArray = array_values($labels);
        
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
