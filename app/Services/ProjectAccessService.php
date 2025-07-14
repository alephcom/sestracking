<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ProjectAccessService
{
    /**
     * Get all projects that the user has access to
     */
    public function getAccessibleProjects(User $user): Collection
    {
        return $user->isAdmin() 
            ? Project::all() 
            : $user->projects;
    }

    /**
     * Get array of project IDs that the user has access to
     */
    public function getAccessibleProjectIds(User $user): array
    {
        return $this->getAccessibleProjects($user)->pluck('id')->toArray();
    }

    /**
     * Check if user has access to a specific project ID
     */
    public function hasAccessToProjectId(User $user, int $projectId): bool
    {
        return in_array($projectId, $this->getAccessibleProjectIds($user));
    }
}