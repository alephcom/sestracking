<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\SesSuppressionService;
use Aws\Exception\AwsException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PushSuppressedDestinationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(
        public int $projectId,
        public string $email,
        public string $reason,
    ) {}

    public function handle(SesSuppressionService $suppression): void
    {
        $project = Project::find($this->projectId);
        if (! $project) {
            return;
        }

        try {
            $suppression->putSuppressedDestination($project, $this->email, $this->reason);
        } catch (AwsException $e) {
            Log::warning('PushSuppressedDestinationJob: SES API error', [
                'project_id' => $this->projectId,
                'email' => $this->email,
                'reason' => $this->reason,
                'aws_error_code' => $e->getAwsErrorCode(),
                'message' => $e->getMessage(),
            ]);

            if (SesSuppressionService::isNonRetryablePutFailure($e)) {
                return;
            }

            throw $e;
        } catch (InvalidArgumentException $e) {
            Log::warning('PushSuppressedDestinationJob: configuration error (not retrying)', [
                'project_id' => $this->projectId,
                'email' => $this->email,
                'reason' => $this->reason,
                'message' => $e->getMessage(),
            ]);

            return;
        } catch (\Throwable $e) {
            Log::error('PushSuppressedDestinationJob: unexpected error', [
                'project_id' => $this->projectId,
                'email' => $this->email,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
