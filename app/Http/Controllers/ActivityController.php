<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\Project;
use App\Services\ProjectAccessService;
use App\Utils\ActivityExport\Report;
use App\Utils\ActivityExport\WriterFormatFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Carbon\Carbon;
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

        $filters = [];

        if ($request->get('search')) {
            $filters['search'] = $request->get('search');
        }

        if ($request->get('eventType')) {
            $filters['eventType'] = $request->get('eventType');
        }

        if ($dateFrom = $request->get('dateFrom')) {
          $filters['dateFrom']  = Carbon::parse($dateFrom);  
        }

        if ($dateTo = $request->get('dateTo')) {
            $filters['dateTo']  = Carbon::parse($dateTo);
        }

        // Build query with validated project IDs only
        $email = Email::with('recipients')->whereIn('project_id', $selectedProjectIds);

        if (!empty($filters['search'])){
            $email->where(function($query) use ($filters) {
                $query->whereHas('recipients', function($q) use ($filters) {
                    $q->where('address', 'like', '%' . $filters['search'] . '%');
                })->orWhere('subject', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['dateFrom']) && !empty($filters['dateTo'])) {
            $email->whereBetween('sent_at', [$filters['dateFrom'], $filters['dateTo']]);
        }

        if (!empty($filters['eventType'])) {
            $email->whereHas('events', function($query) use ($filters) {
                $query->where('type', $filters['eventType']);
            });
        }   

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

        $filters = [];

        if ($request->get('search')) {
            $filters['search'] = $request->get('search');
        }

        if ($request->get('eventType')) {
            $filters['eventType'] = $request->get('eventType');
        }

        if ($dateFrom = $request->get('dateFrom')) {
            try {
                $filters['dateFrom']  = Carbon::parse($dateFrom);
            } catch (\Exception $e) {
                throw new \Exception('Wrong dateFrom parameter!');
            }
        }

        if ($dateTo = $request->get('dateTo')) {
            try {
                $filters['dateTo']  = Carbon::parse($dateTo);
            } catch (\Exception $e) {
                throw new \Exception('Wrong dateTo parameter!');
            }
        }


        // Build export query with validated project IDs only
        $emails = Email::whereIn('project_id', $selectedProjectIds);

        if (!empty($filters['search'])){
            $emails->where(function($query) use ($filters) {
                $query->where('emails.destination', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('emails.subject', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['dateFrom']) && !empty($filters['dateTo'])) {
            $emails->whereBetween('emails.created_at', [$filters['dateFrom'], $filters['dateTo']]);
        }

        if (!empty($filters['eventType'])) {
            $emails->leftJoin('email_events','emails.id','=','email_events.email_id')
            ->where('email_events.event_type', $filters['eventType'])
                  ->select('emails.*');
        } 


        $emails = $emails->get();
        $reports = [];
        foreach ($emails as $email) {
                $row = [
                    $email->status ?? '',
                    $email->subject ?? '',
                    is_array($email->destination) ? implode(', ', $email->destination) : ($email->destination ?? ''),
                    $email->created_at ? $email->created_at->format('m/d/Y H:i') : '',
                    $email->opens ?? 0,
                    $email->clicks ?? 0,
                ];
            $reports[] = $row;
        }

        try {
            $writer = $writerFormatFactory->get($request->get('format'));
        } catch (\Exception $e) {
           return response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }


        $response = new StreamedResponse(static function () use ($writer, $reports): void {
            $writer->openToFile('php://output');

            $writer->addRow(WriterEntityFactory::createRowFromArray([
                'Status',
                'Subject',
                'Destination',
                'Date UTC',
                'Opens',
                'Clicks',
            ]));

            foreach ($reports as $row) {
                $writer->addRow(WriterEntityFactory::createRowFromArray($row));
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
