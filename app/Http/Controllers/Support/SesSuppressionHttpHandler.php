<?php

namespace App\Http\Controllers\Support;

use App\Models\Project;
use App\Services\SesSuppressionListMirror;
use App\Services\SesSuppressionService;
use Aws\Exception\AwsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class SesSuppressionHttpHandler
{
    public const USER_FACING_CONFIG_ERROR = 'Per-project AWS Access Key ID, Secret Access Key, and a resolvable region are required for the suppression list (global .env keys are not used). Configure them on the project edit screen.';

    public function __construct(
        private readonly SesSuppressionService $suppression,
        private readonly SesSuppressionListMirror $mirror
    ) {}

    /**
     * @return array{type: 'success'|'error', message: string}
     */
    public function store(Project $project, string $email, string $reason): array
    {
        try {
            $normalized = strtolower($email);
            $this->suppression->putSuppressedDestination(
                $project,
                $normalized,
                $reason
            );
            $this->mirror->upsertEmailFromAws($project, $normalized);
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
        $normalized = strtolower($email);

        try {
            $this->suppression->deleteSuppressedDestination($project, $normalized);
        } catch (InvalidArgumentException $e) {
            Log::warning('SesSuppressionHttpHandler: destroy configuration error', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            return ['type' => 'error', 'message' => self::USER_FACING_CONFIG_ERROR];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NotFoundException') {
                $this->mirror->deleteLocal($project, $normalized);

                return ['type' => 'success', 'message' => 'Address was not on the list (already removed).'];
            }

            return ['type' => 'error', 'message' => 'Could not remove address: '.$e->getMessage()];
        }

        $this->mirror->deleteLocal($project, $normalized);

        return ['type' => 'success', 'message' => 'Address removed from the suppression list.'];
    }

    /**
     * @return array<string, string|null>
     */
    public function redirectListQuery(Request $request): array
    {
        return array_filter([
            'q' => $request->input('q') ?? $request->query('q'),
            'sort' => $request->input('sort') ?? $request->query('sort'),
            'direction' => $request->input('direction') ?? $request->query('direction'),
            'page' => $request->input('page') ?? $request->query('page'),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
