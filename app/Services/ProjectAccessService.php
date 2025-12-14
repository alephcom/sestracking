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
        // Super admins have access to all projects
        if ($user->isSuperAdmin()) {
            return Project::all();
        }
        
        return $user->projects;
    }
    
    /**
     * Check if user is admin for a specific project
     */
    public function isAdminForProject(User $user, Project $project): bool
    {
        return $user->isAdminForProject($project);
    }
    
    /**
     * Get all projects where user is admin
     */
    public function getAdminProjects(User $user): Collection
    {
        return $user->projects()->wherePivot('role', User::ROLE_ADMIN)->get();
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