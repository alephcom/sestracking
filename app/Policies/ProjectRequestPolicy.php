<?php

namespace App\Policies;

use App\Models\ProjectRequest;
use App\Models\User;

class ProjectRequestPolicy
{
    /**
     * Determine whether the user can view the list of project requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view a project request.
     */
    public function view(User $user, ProjectRequest $projectRequest): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can approve a project request.
     */
    public function approve(User $user, ProjectRequest $projectRequest): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can reject a project request.
     */
    public function reject(User $user, ProjectRequest $projectRequest): bool
    {
        return $user->isSuperAdmin();
    }
}
