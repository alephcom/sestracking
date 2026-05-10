<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SesSuppressedDestination;
use Aws\Exception\AwsException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SesSuppressionListSyncer
{
    private const PAGE_SIZE = 100;

    public function __construct(private readonly SesSuppressionService $suppression) {}

    public function syncAllProjects(): void
    {
        $groups = [];
        foreach (Project::query()->orderBy('id')->cursor() as $project) {
            $key = $this->suppressionListScopeKey($project);
            if ($key === null) {
                continue;
            }
            $groups[$key][] = $project;
        }

        foreach ($groups as $key => $projects) {
            /** @var list<Project> $projects */
            $primary = $projects[0];
            $marker = Carbon::now();

            try {
                $token = null;
                do {
                    $page = $this->suppression->listSuppressedDestinations($primary, $token, self::PAGE_SIZE);
                    foreach ($projects as $project) {
                        $this->upsertSummaries($project, $page['summaries'], $marker);
                    }
                    $token = $page['next_token'];
                } while ($token !== null && $token !== '');

                foreach ($projects as $project) {
                    SesSuppressedDestination::query()
                        ->where('project_id', $project->id)
                        ->where('synced_at', '<', $marker)
                        ->delete();

                    $project->forceFill(['ses_suppression_list_synced_at' => $marker])->save();
                }
            } catch (AwsException $e) {
                Log::warning('SesSuppressionListSyncer: SES API error', [
                    'scope_key' => $key,
                    'project_ids' => array_map(fn (Project $p) => $p->id, $projects),
                    'aws_error_code' => $e->getAwsErrorCode(),
                    'message' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::error('SesSuppressionListSyncer: unexpected error', [
                    'scope_key' => $key,
                    'project_ids' => array_map(fn (Project $p) => $p->id, $projects),
                    'exception' => $e,
                ]);

                throw $e;
            }
        }
    }

    /**
     * @param  list<array{email: string, reason: string, last_update_time: ?string}>  $summaries
     */
    private function upsertSummaries(Project $project, array $summaries, Carbon $marker): void
    {
        $now = Carbon::now();
        $batch = [];

        foreach ($summaries as $row) {
            $email = strtolower(trim($row['email']));
            if ($email === '') {
                continue;
            }

            $batch[] = [
                'project_id' => $project->id,
                'email' => $email,
                'reason' => $row['reason'],
                'last_update_time' => isset($row['last_update_time']) && $row['last_update_time'] !== ''
                    ? Carbon::parse($row['last_update_time'])
                    : null,
                'synced_at' => $marker,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            SesSuppressedDestination::query()->upsert(
                $chunk,
                ['project_id', 'email'],
                ['reason', 'last_update_time', 'synced_at', 'updated_at']
            );
        }
    }

    /**
     * Projects sharing the same SES account (region + credential identity) are listed once; rows are replicated per project for reporting.
     */
    private function suppressionListScopeKey(Project $project): ?string
    {
        if (! $project->canRunSesSuppressionApi()) {
            return null;
        }

        return 'p:'.$project->resolvedSesSuppressionRegion().':'.$project->ses_aws_access_key_id;
    }
}
