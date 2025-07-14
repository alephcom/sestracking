<?php

namespace App\Http\Controllers;

use App\Enums\EmailEnums;
use App\Enums\EmailEventEnums;
use App\Models\Email;
use App\Models\EmailEvent;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebHookController extends Controller
{
    public function index(Request $request, $token)
    {
        // Debug: Log incoming payload
        $rawPayload = $request->getContent();
        $timestamp = now()->format('Y-m-d H:i:s');
        $logEntry = "[$timestamp] Incoming webhook payload: " . $rawPayload . PHP_EOL;
        file_put_contents(storage_path('logs/webhook_debug.log'), $logEntry, FILE_APPEND | LOCK_EX);

        $jsonData = json_decode($request->getContent(), true);

        if ($jsonData === false) {
            return response('Error', 400);
        }

        // Auto subscribe to SNS topic.
        if (!empty($jsonData['Type']) && $jsonData['Type'] == 'SubscriptionConfirmation') {
            $response = Http::get($jsonData['SubscribeURL']);
            if ($response->successfull()) {
                return response('Ok');
            }
            return response('Not Ok', 400);
        }

        // Handle SNS message format
        if (!empty($jsonData['Message'])) {
            $jsonData = json_decode($jsonData['Message'], true);
        }

        $project = Project::where('token', $token)->first();

        if(!$project){
            return response('Project not found', 404);
        }
        // Process mail.
        // Try to find mail.
        $email = Email::where([
            'project_id' => $project->id,
            'message_id' => $jsonData['mail']['messageId']
        ])->first();

        // Create new mail.
        if (!$email) {
            $email = new Email();
            $email->project_id = $project->id;
            $email->message_id = $jsonData['mail']['messageId'];
            $email->destination = json_encode($jsonData['mail']['destination']);
            $email->source = $jsonData['mail']['source'];
            $email->subject = $jsonData['mail']['commonHeaders']['subject'] ??'N/A';
            $email->status = EmailEnums::EMAIL_STATUS_SENT;
            $email->opens = 0;
            $email->clicks = 0;
            $email->created_at = new \DateTime($jsonData['mail']['timestamp']);
            $email->save();
        }

        try {
            $emailEvent = new EmailEvent();
            $emailEvent->email_id = $email->id;
            $eventType = null;
            if (!empty($jsonData['eventType'])) {
                $eventType = $jsonData['eventType'];
            }

            else if (!empty($jsonData['notificationType'])) {
                $eventType =  $jsonData['notificationType'];
            }
            $eventData = null;

        switch ($eventType) {
            case 'Send':
                $email->status = EmailEnums::EMAIL_STATUS_SENT;
                $eventData = $jsonData[EmailEventEnums::EVENT_SEND];
                break;

            case 'Delivery':
                $email->status = EmailEnums::EMAIL_STATUS_DELIVERED;
                $eventData = $jsonData[EmailEventEnums::EVENT_DELIVERY];
                break;

            case 'Reject':
                $email->status = EmailEnums::EMAIL_STATUS_NOT_DELIVERED;
                $eventData = $jsonData[EmailEventEnums::EVENT_REJECT];
                break;

            case 'Bounce':
                $email->status = EmailEnums::EMAIL_STATUS_NOT_DELIVERED;
                $eventData = $jsonData[EmailEventEnums::EVENT_BOUNCE];
                break;

            case 'Complaint':
                $email->status = EmailEnums::EMAIL_STATUS_DELIVERED;
                $eventData = $jsonData[EmailEventEnums::EVENT_COMPLAINT];
                break;

            case 'Rendering Failure':
                $email->status = EmailEnums::EMAIL_STATUS_NOT_DELIVERED;
                $eventData = $jsonData[EmailEventEnums::EVENT_FAILURE];
                break;

            case 'Open':
                $eventData = $jsonData[EmailEventEnums::EVENT_OPEN];
                $email->opens++;
                break;

            case 'Click':
                $eventData = $jsonData[EmailEventEnums::EVENT_CLICK];
                $email->clicks++;
                break;

            default:
                throw new \Exception('Unexpected value');
        }

        $email->save();
        $emailEvent->event_type = strtolower( $eventType );
        $emailEvent->event_data = json_encode( $eventData );
        $emailEvent->save();

        } catch (\Exception $e) {
            return response($e->getMessage(), 400);
        }


        return response('Ok');

    }
}
