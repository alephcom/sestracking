<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Services\ProjectAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index(ProjectAccessService $projectService)
    {
        $accessibleProjects = $projectService->getAccessibleProjects(auth()->user());
        
        // Default date range: last 30 days
        $defaultStartDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $defaultEndDate = Carbon::now()->format('Y-m-d');
        
        return view('reports.index', compact('accessibleProjects', 'defaultStartDate', 'defaultEndDate'));
    }

    /**
     * Report 1: List all emails with status, opens, and clicks
     */
    public function emailsReport(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);
        
        // Validate and get filters
        $request->validate([
            'projectId' => 'nullable|string',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
        ]);

        $projectId = $request->get('projectId', 'all');
        $dateFrom = Carbon::parse($request->get('dateFrom'))->startOfDay();
        $dateTo = Carbon::parse($request->get('dateTo'))->endOfDay();

        // Determine which projects to include
        if ($projectId === 'all' || empty($projectId)) {
            $selectedProjectIds = $accessibleProjectIds;
        } else {
            // Handle comma-separated project IDs
            if (strpos($projectId, ',') !== false) {
                $requestedIds = array_map('trim', explode(',', $projectId));
            } else {
                $requestedIds = [$projectId];
            }
            
            // Filter to only include accessible project IDs
            $selectedProjectIds = array_intersect(
                array_map('intval', $requestedIds),
                $accessibleProjectIds
            );
        }

        if (empty($selectedProjectIds)) {
            return response()->json(['error' => 'No accessible projects selected'], 403);
        }

        // Query emails with aggregated open and click counts
        $emails = Email::whereIn('project_id', $selectedProjectIds)
            ->whereBetween('sent_at', [$dateFrom, $dateTo])
            ->with(['project', 'recipients'])
            ->withCount([
                'events as opens_count' => function ($query) {
                    $query->where('type', 'open');
                },
                'events as clicks_count' => function ($query) {
                    $query->where('type', 'click');
                }
            ])
            ->withCount('recipients as recipient_count')
            ->orderBy('sent_at', 'desc')
            ->get()
            ->map(function ($email) {
                return [
                    'id' => $email->id,
                    'project_name' => $email->project->name,
                    'subject' => $email->subject ?? '(No subject)',
                    'source' => $email->source,
                    'sent_at' => $email->sent_at ? $email->sent_at->format('Y-m-d H:i:s') : '',
                    'status' => $email->status,
                    'opens' => (int) ($email->opens_count ?? 0),
                    'clicks' => (int) ($email->clicks_count ?? 0),
                    'recipient_count' => (int) ($email->recipient_count ?? 0),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $emails,
            'filters' => [
                'projectIds' => $selectedProjectIds,
                'dateFrom' => $dateFrom->format('Y-m-d'),
                'dateTo' => $dateTo->format('Y-m-d'),
            ]
        ]);
    }

    /**
     * Report 2: List all mail recipients with total emails sent, total opens, and total clicks
     */
    public function recipientsReport(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);
        
        // Validate and get filters
        $request->validate([
            'projectId' => 'nullable|string',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
        ]);

        $projectId = $request->get('projectId', 'all');
        $dateFrom = Carbon::parse($request->get('dateFrom'))->startOfDay();
        $dateTo = Carbon::parse($request->get('dateTo'))->endOfDay();

        // Determine which projects to include
        if ($projectId === 'all' || empty($projectId)) {
            $selectedProjectIds = $accessibleProjectIds;
        } else {
            // Handle comma-separated project IDs
            if (strpos($projectId, ',') !== false) {
                $requestedIds = array_map('trim', explode(',', $projectId));
            } else {
                $requestedIds = [$projectId];
            }
            
            // Filter to only include accessible project IDs
            $selectedProjectIds = array_intersect(
                array_map('intval', $requestedIds),
                $accessibleProjectIds
            );
        }

        if (empty($selectedProjectIds)) {
            return response()->json(['error' => 'No accessible projects selected'], 403);
        }

        // Query recipients with aggregated statistics
        // First get recipients within the project and date range
        $recipients = EmailRecipient::whereHas('email', function ($query) use ($selectedProjectIds, $dateFrom, $dateTo) {
                $query->whereIn('project_id', $selectedProjectIds)
                      ->whereBetween('sent_at', [$dateFrom, $dateTo]);
            })
            ->select('email_recipients.address')
            ->selectRaw('COUNT(DISTINCT email_recipients.email_id) as total_emails')
            ->selectRaw('COALESCE(SUM(CASE WHEN recipient_events.type = "open" THEN 1 ELSE 0 END), 0) as total_opens')
            ->selectRaw('COALESCE(SUM(CASE WHEN recipient_events.type = "click" THEN 1 ELSE 0 END), 0) as total_clicks')
            ->leftJoin('recipient_events', 'email_recipients.id', '=', 'recipient_events.recipient_id')
            ->groupBy('email_recipients.address')
            ->orderBy('total_emails', 'desc')
            ->orderBy('email_recipients.address', 'asc')
            ->get()
            ->map(function ($recipient) {
                return [
                    'address' => $recipient->address,
                    'total_emails' => (int) $recipient->total_emails,
                    'total_opens' => (int) $recipient->total_opens,
                    'total_clicks' => (int) $recipient->total_clicks,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $recipients,
            'filters' => [
                'projectIds' => $selectedProjectIds,
                'dateFrom' => $dateFrom->format('Y-m-d'),
                'dateTo' => $dateTo->format('Y-m-d'),
            ]
        ]);
    }

    /**
     * Report 3: List all email senders with total opens, total clicks, and status counts
     */
    public function sendersReport(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);
        
        // Validate and get filters
        $request->validate([
            'projectId' => 'nullable|string',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
        ]);

        $projectId = $request->get('projectId', 'all');
        $dateFrom = Carbon::parse($request->get('dateFrom'))->startOfDay();
        $dateTo = Carbon::parse($request->get('dateTo'))->endOfDay();

        // Determine which projects to include
        if ($projectId === 'all' || empty($projectId)) {
            $selectedProjectIds = $accessibleProjectIds;
        } else {
            // Handle comma-separated project IDs
            if (strpos($projectId, ',') !== false) {
                $requestedIds = array_map('trim', explode(',', $projectId));
            } else {
                $requestedIds = [$projectId];
            }
            
            // Filter to only include accessible project IDs
            $selectedProjectIds = array_intersect(
                array_map('intval', $requestedIds),
                $accessibleProjectIds
            );
        }

        if (empty($selectedProjectIds)) {
            return response()->json(['error' => 'No accessible projects selected'], 403);
        }

        // Get emails with their events
        $emails = Email::whereIn('project_id', $selectedProjectIds)
            ->whereBetween('sent_at', [$dateFrom, $dateTo])
            ->withCount([
                'events as opens_count' => function ($query) {
                    $query->where('type', 'open');
                },
                'events as clicks_count' => function ($query) {
                    $query->where('type', 'click');
                }
            ])
            ->with('recipients')
            ->get();

        // Aggregate by sender (source)
        $sendersData = [];
        
        foreach ($emails as $email) {
            $source = $email->source ?? '(Unknown)';
            
            if (!isset($sendersData[$source])) {
                $sendersData[$source] = [
                    'sender' => $source,
                    'total_emails' => 0,
                    'total_opens' => 0,
                    'total_clicks' => 0,
                    'status_counts' => [
                        'delivered' => 0,
                        'sent' => 0,
                        'bounced' => 0,
                        'complained' => 0,
                    ]
                ];
            }
            
            $sendersData[$source]['total_emails']++;
            $sendersData[$source]['total_opens'] += (int) ($email->opens_count ?? 0);
            $sendersData[$source]['total_clicks'] += (int) ($email->clicks_count ?? 0);
            
            // Count statuses from recipients
            $status = $email->status;
            if (isset($sendersData[$source]['status_counts'][$status])) {
                $sendersData[$source]['status_counts'][$status]++;
            } else {
                // Handle unknown statuses
                if (!isset($sendersData[$source]['status_counts']['other'])) {
                    $sendersData[$source]['status_counts']['other'] = 0;
                }
                $sendersData[$source]['status_counts']['other']++;
            }
        }

        // Convert to array and sort
        $senders = collect($sendersData)->map(function ($data) {
            return [
                'sender' => $data['sender'],
                'total_emails' => $data['total_emails'],
                'total_opens' => $data['total_opens'],
                'total_clicks' => $data['total_clicks'],
                'status_delivered' => $data['status_counts']['delivered'] ?? 0,
                'status_sent' => $data['status_counts']['sent'] ?? 0,
                'status_bounced' => $data['status_counts']['bounced'] ?? 0,
                'status_complained' => $data['status_counts']['complained'] ?? 0,
                'status_other' => $data['status_counts']['other'] ?? 0,
            ];
        })
        ->sortByDesc('total_emails')
        ->values()
        ->toArray();

        return response()->json([
            'success' => true,
            'data' => $senders,
            'filters' => [
                'projectIds' => $selectedProjectIds,
                'dateFrom' => $dateFrom->format('Y-m-d'),
                'dateTo' => $dateTo->format('Y-m-d'),
            ]
        ]);
    }
}
