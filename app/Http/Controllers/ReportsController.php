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
        
        $defaultStartDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $defaultEndDate = Carbon::now()->format('Y-m-d');
        
        return view('reports.index', compact('accessibleProjects', 'defaultStartDate', 'defaultEndDate'));
    }

    /**
     * Resolve and validate project IDs from a request against accessible IDs.
     * Returns null when the resolved set is empty (caller should return 403).
     */
    private function resolveProjectIds(Request $request, array $accessibleProjectIds): ?array
    {
        $projectId = $request->get('projectId', 'all');

        if ($projectId === 'all' || empty($projectId)) {
            return $accessibleProjectIds;
        }

        $requestedIds = strpos($projectId, ',') !== false
            ? array_map('trim', explode(',', $projectId))
            : [$projectId];

        $selected = array_intersect(array_map('intval', $requestedIds), $accessibleProjectIds);

        return empty($selected) ? null : array_values($selected);
    }

    /**
     * Report 1: List all emails with status, opens, and clicks.
     *
     * Uses a single JOIN query to compute status and aggregates in SQL,
     * avoiding loading all recipients and events as Eloquent collections.
     */
    public function emailsReport(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);

        $request->validate([
            'projectId' => 'nullable|string',
            'dateFrom'  => 'required|date',
            'dateTo'    => 'required|date|after_or_equal:dateFrom',
        ]);

        $selectedProjectIds = $this->resolveProjectIds($request, $accessibleProjectIds);
        if ($selectedProjectIds === null) {
            return response()->json(['error' => 'No accessible projects selected'], 403);
        }

        $dateFrom = Carbon::parse($request->get('dateFrom'))->startOfDay();
        $dateTo   = Carbon::parse($request->get('dateTo'))->endOfDay();

        $emails = DB::table('emails as e')
            ->join('projects as p', 'e.project_id', '=', 'p.id')
            ->leftJoin('email_recipients as er', 'e.id', '=', 'er.email_id')
            ->leftJoin('recipient_events as re', 'er.id', '=', 're.recipient_id')
            ->select('e.id', 'e.subject', 'e.source', 'e.sent_at', 'p.name as project_name')
            ->selectRaw('COUNT(DISTINCT er.id) as recipient_count')
            ->selectRaw("SUM(CASE WHEN re.type = 'open' THEN 1 ELSE 0 END) as opens_count")
            ->selectRaw("SUM(CASE WHEN re.type = 'click' THEN 1 ELSE 0 END) as clicks_count")
            ->selectRaw("CASE
                WHEN MAX(CASE WHEN er.status IN ('bounced', 'rejected') THEN 1 ELSE 0 END) = 1 THEN 'bounced'
                WHEN MAX(CASE WHEN er.status = 'complained' THEN 1 ELSE 0 END) = 1 THEN 'complained'
                WHEN MAX(CASE WHEN er.status = 'delivered' THEN 1 ELSE 0 END) = 1 THEN 'delivered'
                ELSE 'sent'
            END as status")
            ->whereIn('e.project_id', $selectedProjectIds)
            ->whereBetween('e.sent_at', [$dateFrom, $dateTo])
            ->groupBy('e.id', 'e.subject', 'e.source', 'e.sent_at', 'p.name')
            ->orderBy('e.sent_at', 'desc')
            ->get()
            ->map(function ($email) {
                return [
                    'id'               => $email->id,
                    'project_name'     => $email->project_name,
                    'subject'          => $email->subject ?? '(No subject)',
                    'source'           => $email->source,
                    'sent_at'          => $email->sent_at ? Carbon::parse($email->sent_at)->format('Y-m-d H:i:s') : '',
                    'status'           => $email->status,
                    'opens'            => (int) ($email->opens_count ?? 0),
                    'clicks'           => (int) ($email->clicks_count ?? 0),
                    'recipient_count'  => (int) ($email->recipient_count ?? 0),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $emails,
            'filters' => [
                'projectIds' => $selectedProjectIds,
                'dateFrom'   => $dateFrom->format('Y-m-d'),
                'dateTo'     => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Report 2: Aggregated per-address statistics.
     *
     * Replaced whereHas (EXISTS subquery) with a direct JOIN, and fixed
     * double-quoted string literals that are non-portable to strict MySQL.
     */
    public function recipientsReport(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);

        $request->validate([
            'projectId' => 'nullable|string',
            'dateFrom'  => 'required|date',
            'dateTo'    => 'required|date|after_or_equal:dateFrom',
        ]);

        $selectedProjectIds = $this->resolveProjectIds($request, $accessibleProjectIds);
        if ($selectedProjectIds === null) {
            return response()->json(['error' => 'No accessible projects selected'], 403);
        }

        $dateFrom = Carbon::parse($request->get('dateFrom'))->startOfDay();
        $dateTo   = Carbon::parse($request->get('dateTo'))->endOfDay();

        $recipients = DB::table('email_recipients as er')
            ->join('emails as e', 'er.email_id', '=', 'e.id')
            ->leftJoin('recipient_events as re', 'er.id', '=', 're.recipient_id')
            ->select('er.address')
            ->selectRaw('COUNT(DISTINCT er.email_id) as total_emails')
            ->selectRaw("COALESCE(SUM(CASE WHEN re.type = 'open' THEN 1 ELSE 0 END), 0) as total_opens")
            ->selectRaw("COALESCE(SUM(CASE WHEN re.type = 'click' THEN 1 ELSE 0 END), 0) as total_clicks")
            ->whereIn('e.project_id', $selectedProjectIds)
            ->whereBetween('e.sent_at', [$dateFrom, $dateTo])
            ->groupBy('er.address')
            ->orderByDesc('total_emails')
            ->orderBy('er.address')
            ->get()
            ->map(function ($recipient) {
                return [
                    'address'       => $recipient->address,
                    'total_emails'  => (int) $recipient->total_emails,
                    'total_opens'   => (int) $recipient->total_opens,
                    'total_clicks'  => (int) $recipient->total_clicks,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $recipients,
            'filters' => [
                'projectIds' => $selectedProjectIds,
                'dateFrom'   => $dateFrom->format('Y-m-d'),
                'dateTo'     => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Report 3: Per-sender aggregation.
     *
     * Previously loaded every email + recipients + events into PHP for grouping.
     * Now uses a two-level SQL aggregation (subquery → outer GROUP BY source)
     * so only one row per unique sender is returned to PHP.
     */
    public function sendersReport(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);

        $request->validate([
            'projectId' => 'nullable|string',
            'dateFrom'  => 'required|date',
            'dateTo'    => 'required|date|after_or_equal:dateFrom',
        ]);

        $selectedProjectIds = $this->resolveProjectIds($request, $accessibleProjectIds);
        if ($selectedProjectIds === null) {
            return response()->json(['error' => 'No accessible projects selected'], 403);
        }

        $dateFrom = Carbon::parse($request->get('dateFrom'))->startOfDay();
        $dateTo   = Carbon::parse($request->get('dateTo'))->endOfDay();

        // Inner query: one row per email with its computed status, opens, and clicks
        $perEmailQuery = DB::table('emails as e')
            ->leftJoin('email_recipients as er', 'e.id', '=', 'er.email_id')
            ->leftJoin('recipient_events as re', 'er.id', '=', 're.recipient_id')
            ->select('e.id', 'e.source')
            ->selectRaw("SUM(CASE WHEN re.type = 'open' THEN 1 ELSE 0 END) as opens_count")
            ->selectRaw("SUM(CASE WHEN re.type = 'click' THEN 1 ELSE 0 END) as clicks_count")
            ->selectRaw("CASE
                WHEN MAX(CASE WHEN er.status IN ('bounced', 'rejected') THEN 1 ELSE 0 END) = 1 THEN 'bounced'
                WHEN MAX(CASE WHEN er.status = 'complained' THEN 1 ELSE 0 END) = 1 THEN 'complained'
                WHEN MAX(CASE WHEN er.status = 'delivered' THEN 1 ELSE 0 END) = 1 THEN 'delivered'
                ELSE 'sent'
            END as email_status")
            ->whereIn('e.project_id', $selectedProjectIds)
            ->whereBetween('e.sent_at', [$dateFrom, $dateTo])
            ->groupBy('e.id', 'e.source');

        // Outer query: aggregate per sender
        $senders = DB::table($perEmailQuery, 'email_data')
            ->select('source')
            ->selectRaw('COUNT(*) as total_emails')
            ->selectRaw('SUM(opens_count) as total_opens')
            ->selectRaw('SUM(clicks_count) as total_clicks')
            ->selectRaw("SUM(CASE WHEN email_status = 'bounced'   THEN 1 ELSE 0 END) as status_bounced")
            ->selectRaw("SUM(CASE WHEN email_status = 'complained' THEN 1 ELSE 0 END) as status_complained")
            ->selectRaw("SUM(CASE WHEN email_status = 'delivered'  THEN 1 ELSE 0 END) as status_delivered")
            ->selectRaw("SUM(CASE WHEN email_status = 'sent'       THEN 1 ELSE 0 END) as status_sent")
            ->groupBy('source')
            ->orderByDesc('total_emails')
            ->get()
            ->map(function ($row) {
                return [
                    'sender'             => $row->source ?? '(Unknown)',
                    'total_emails'       => (int) $row->total_emails,
                    'total_opens'        => (int) $row->total_opens,
                    'total_clicks'       => (int) $row->total_clicks,
                    'status_delivered'   => (int) $row->status_delivered,
                    'status_sent'        => (int) $row->status_sent,
                    'status_bounced'     => (int) $row->status_bounced,
                    'status_complained'  => (int) $row->status_complained,
                    'status_other'       => 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $senders,
            'filters' => [
                'projectIds' => $selectedProjectIds,
                'dateFrom'   => $dateFrom->format('Y-m-d'),
                'dateTo'     => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Report 4: Bounced recipients with bounce type and subtype.
     *
     * Replaced whereHas + eager-loaded relationship chain with a direct JOIN
     * that selects only the columns needed for the response.
     */
    public function bouncedRecipientsReport(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);

        $request->validate([
            'projectId' => 'nullable|string',
            'dateFrom'  => 'required|date',
            'dateTo'    => 'required|date|after_or_equal:dateFrom',
        ]);

        $selectedProjectIds = $this->resolveProjectIds($request, $accessibleProjectIds);
        if ($selectedProjectIds === null) {
            return response()->json(['error' => 'No accessible projects selected'], 403);
        }

        $dateFrom = Carbon::parse($request->get('dateFrom'))->startOfDay();
        $dateTo   = Carbon::parse($request->get('dateTo'))->endOfDay();

        $bounceEvents = DB::table('recipient_events as re')
            ->join('email_recipients as er', 're.recipient_id', '=', 'er.id')
            ->join('emails as e', 'er.email_id', '=', 'e.id')
            ->join('projects as p', 'e.project_id', '=', 'p.id')
            ->select('re.event_at', 're.payload', 'er.address', 'e.subject', 'e.source', 'p.name as project_name')
            ->where('re.type', 'bounce')
            ->whereBetween('re.event_at', [$dateFrom, $dateTo])
            ->whereIn('e.project_id', $selectedProjectIds)
            ->orderBy('re.event_at', 'desc')
            ->get()
            ->map(function ($event) {
                $payload = json_decode($event->payload ?? '{}', true) ?? [];
                $bounce = $payload['bounce'] ?? [];

                return [
                    'recipient_address' => $event->address,
                    'bounce_type'       => $bounce['bounceType'] ?? 'Unknown',
                    'bounce_subtype'    => $bounce['bounceSubType'] ?? 'Unknown',
                    'bounced_at'        => $event->event_at ? Carbon::parse($event->event_at)->format('Y-m-d H:i:s') : '',
                    'project_name'      => $event->project_name,
                    'email_subject'     => $event->subject ?? '(No subject)',
                    'email_source'      => $event->source ?? 'Unknown',
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $bounceEvents->values()->toArray(),
            'filters' => [
                'projectIds' => $selectedProjectIds,
                'dateFrom'   => $dateFrom->format('Y-m-d'),
                'dateTo'     => $dateTo->format('Y-m-d'),
            ],
        ]);
    }
}
