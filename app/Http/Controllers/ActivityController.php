<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\Project;
use App\Services\ProjectAccessService;
use App\Utils\ActivityExport\Report;
use App\Utils\ActivityExport\WriterFormatFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(ProjectAccessService $projectService)
    {
        $accessibleProjects = $projectService->getAccessibleProjects(auth()->user());
        return view('activity.index', compact('accessibleProjects'));
    }

    public function listApi(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);
        
        // SECURITY: Validate project access - never trust user input
        $requestedProjectId = $request->get('project_id');
        
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
                'rows' => [],
                'totalRows' => 0,
            ]);
        }

        $email = $this->filteredEmailQuery($request, $selectedProjectIds)->with('recipients');

        $results = $email->orderBy('sent_at','desc')->paginate(10);

        // Transform data to include destination array from recipients
        $transformedRows = $results->getCollection()->map(function ($email) {
            $emailArray = $email->toArray();
            $emailArray['destination'] = $email->recipients->pluck('address')->toArray();
            $emailArray['status'] = $this->getEmailStatus($email);
            return $emailArray;
        });

        return response()->json([
            'rows' => $transformedRows,
            'totalRows' => $results->total(),
        ]);
    }


    public function detailsApi(Request $request, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);
        
        // SECURITY: Validate that the email belongs to an accessible project
        $email = Email::whereIn('project_id', $accessibleProjectIds)
                     ->with(['project', 'recipients'])
                     ->with(['events' => function($query) {
                         $query->orderBy('event_at', 'asc'); // Sort events older to newer
                     }])
                     ->find($request->id);
                     
        if (!$email) {
            return response()->json(['error' => 'Email not found or access denied'], 404);
        }
        
        // Transform data for frontend compatibility
        $emailArray = $email->toArray();
        $emailArray['messageId'] = $email->message_id; // Frontend expects messageId
        $emailArray['destination'] = $email->recipients->pluck('address')->toArray();
        $emailArray['emailEvents'] = $email->events->map(function($event) {
            return [
                'id' => $event->id,
                'event' => $event->type, // Frontend expects 'event'
                'timestamp' => $event->event_at->toDateTimeString(),
                'eventData' => json_encode($event->payload, JSON_PRETTY_PRINT)
            ];
        });
        
        return response()->json($emailArray);
    }

    public function export(Request $request, WriterFormatFactory $writerFormatFactory, ProjectAccessService $projectService)
    {
        $user = auth()->user();
        $accessibleProjectIds = $projectService->getAccessibleProjectIds($user);
        
        // SECURITY: Validate project access for export
        $requestedProjectId = $request->get('project_id');
        
        if ($requestedProjectId === 'all' || !$requestedProjectId) {
            $selectedProjectIds = $accessibleProjectIds;
        } else {
            if (!in_array($requestedProjectId, $accessibleProjectIds)) {
                abort(403, 'Unauthorized access to project');
            }
            $selectedProjectIds = [$requestedProjectId];
        }

        if (empty($selectedProjectIds)) {
            return response()->json([]);
        }

        // Same filters and data source as the on-screen list (listApi), so exports match what users see.
        $emails = $this->filteredEmailQuery($request, $selectedProjectIds)
            ->with('recipients')
            ->orderBy('sent_at', 'desc')
            ->orderBy('id', 'desc'); // unique tiebreaker so lazy() pages never skip or repeat rows

        try {
            $writer = $writerFormatFactory->get($request->get('format'));
        } catch (\Exception $e) {
           return response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }


        $response = new StreamedResponse(function () use ($writer, $emails): void {
            $writer->openToFile('php://output');

            $writer->addRow(WriterEntityFactory::createRowFromArray([
                'Status',
                'Subject',
                'Destination',
                'Date UTC',
                'Opens',
                'Clicks',
            ]));

            // lazy() pages through the results so large exports are not held in memory at once.
            foreach ($emails->lazy(500) as $email) {
                $writer->addRow(WriterEntityFactory::createRowFromArray([
                    $this->getEmailStatus($email),
                    $email->subject ?? '',
                    $email->recipients->pluck('address')->implode(', '),
                    $email->sent_at ? $email->sent_at->format('m/d/Y H:i') : '',
                    $email->opens ?? 0,
                    $email->clicks ?? 0,
                ]));
            }

            $writer->close();
        });

        // TODO Refactor
        if ($request->get('format') == 'csv') {
             $response->headers->set('Content-Type', 'text/csv');
             $response->headers->set('Content-Disposition', 'attachment; filename="activity_report.csv"');
        }
        else {
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="activity_report.xlsx"');
        }

        return $response;
    }

    /**
     * Emails in the given (already access-checked) projects, filtered by the
     * search / eventType / dateFrom / dateTo request parameters.
     */
    private function filteredEmailQuery(Request $request, array $projectIds): Builder
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'eventType' => 'nullable|string|max:50',
            'dateFrom' => 'nullable|date',
            'dateTo' => 'nullable|date',
        ]);

        $query = Email::whereIn('project_id', $projectIds);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search) {
                $query->whereHas('recipients', function ($q) use ($search) {
                    $q->where('address', 'like', '%'.$search.'%');
                })->orWhere('subject', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['dateFrom']) && ! empty($filters['dateTo'])) {
            $query->whereBetween('sent_at', [Carbon::parse($filters['dateFrom']), Carbon::parse($filters['dateTo'])]);
        }

        if (! empty($filters['eventType'])) {
            // whereIn subquery is more efficient than whereHas (avoids nested EXISTS
            // traversing the hasManyThrough relationship chain)
            $query->whereIn('emails.id', function ($subquery) use ($filters) {
                $subquery->select('er.email_id')
                    ->from('email_recipients as er')
                    ->join('recipient_events as re', 'er.id', '=', 're.recipient_id')
                    ->where('re.type', $filters['eventType']);
            });
        }

        return $query;
    }

    private function getEmailStatus($email)
    {
        $recipientStatuses = $email->recipients->pluck('status')->unique();
        
        if ($recipientStatuses->contains('bounced') || $recipientStatuses->contains('rejected')) {
            return 'bounced';
        }
        if ($recipientStatuses->contains('complained')) {
            return 'complained';
        }
        if ($recipientStatuses->contains('delivered')) {
            return 'delivered';
        }
        
        return 'sent';
    }
}
