<?php

namespace App\Http\Controllers\Support;

use App\Models\Project;
use App\Services\SesSuppressionService;
use Aws\Exception\AwsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class SesSuppressionHttpHandler
{
    public const USER_FACING_CONFIG_ERROR = 'Per-project AWS Access Key ID, Secret Access Key, and a resolvable region are required for the suppression list (global .env keys are not used). Configure them on the project edit screen.';

    public function __construct(
        private readonly SesSuppressionService $suppression
    ) {}

    /**
     * @return array{summaries: array<int, array<string, mixed>>, nextToken: ?string, error: ?string}
     */
    public function listPage(Request $request, Project $project): array
    {
        $summaries = [];
        $nextToken = null;
        $error = null;

        try {
            $page = $this->suppression->listSuppressedDestinations(
                $project,
                $request->query('next_token'),
                25
            );
            $summaries = $page['summaries'];
            $nextToken = $page['next_token'];
        } catch (InvalidArgumentException $e) {
            Log::warning('SesSuppressionHttpHandler: index configuration error', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);
            $error = self::USER_FACING_CONFIG_ERROR;
        } catch (AwsException $e) {
            Log::warning('SesSuppressionHttpHandler: index AWS error', [
                'project_id' => $project->id,
                'aws_error_code' => $e->getAwsErrorCode(),
                'message' => $e->getMessage(),
            ]);
            $error = $e->getMessage();
        }

        return [
            'summaries' => $summaries,
            'nextToken' => $nextToken,
            'error' => $error,
        ];
    }

    /**
     * @return array{type: 'success'|'error', message: string}
     */
    public function store(Project $project, string $email, string $reason): array
    {
        try {
            $this->suppression->putSuppressedDestination(
                $project,
                strtolower($email),
                $reason
            );
        } catch (InvalidArgumentException $e) {
            Log::warning('SesSuppressionHttpHandler: store configuration error', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            return ['type' => 'error', 'message' => self::USER_FACING_CONFIG_ERROR];
        } catch (AwsException $e) {
            return ['type' => 'error', 'message' => 'Could not add address: '.$e->getMessage()];
        }

        return ['type' => 'success', 'message' => 'Address added to the suppression list.'];
    }

    /**
     * @return array{type: 'success'|'error', message: string}
     */
    public function destroy(Project $project, string $email): array
    {
        try {
            $this->suppression->deleteSuppressedDestination($project, strtolower($email));
        } catch (InvalidArgumentException $e) {
            Log::warning('SesSuppressionHttpHandler: destroy configuration error', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            return ['type' => 'error', 'message' => self::USER_FACING_CONFIG_ERROR];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NotFoundException') {
                return ['type' => 'success', 'message' => 'Address was not on the list (already removed).'];
            }

            return ['type' => 'error', 'message' => 'Could not remove address: '.$e->getMessage()];
        }

        return ['type' => 'success', 'message' => 'Address removed from the suppression list.'];
    }
}
