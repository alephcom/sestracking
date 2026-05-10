<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SesSuppressedDestination;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class SesSuppressionListMirror
{
    public function __construct(
        private readonly SesSuppressionService $suppression
    ) {}

    public function upsertEmailFromAws(Project $project, string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }

        try {
            $row = $this->suppression->getSuppressedDestination($project, $email);
            $now = Carbon::now();
            $marker = $now;

            SesSuppressedDestination::query()->upsert(
                [[
                    'project_id' => $project->id,
                    'email' => strtolower((string) ($row['email'] ?? $email)),
                    'reason' => (string) ($row['reason'] ?? ''),
                    'last_update_time' => isset($row['last_update_time']) && $row['last_update_time'] !== ''
                        ? Carbon::parse($row['last_update_time'])
                        : null,
                    'synced_at' => $marker,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['project_id', 'email'],
                ['reason', 'last_update_time', 'synced_at', 'updated_at']
            );
        } catch (\Throwable $e) {
            Log::warning('SesSuppressionListMirror: upsert after put skipped', [
                'project_id' => $project->id,
                'email' => $email,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function deleteLocal(Project $project, string $email): void
    {
        SesSuppressedDestination::query()
            ->where('project_id', $project->id)
            ->where('email', strtolower(trim($email)))
            ->delete();
    }
}
