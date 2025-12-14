<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Role constants for project_user pivot table
    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'super_admin' => 'boolean',
        ];
    }

    /**
     * Get projects that the user has access to
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class)->withPivot('role')->withTimestamps();
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->super_admin === true;
    }

    /**
     * Check if user is admin for a specific project
     */
    public function isAdminForProject(Project $project): bool
    {
        // Super admins are admin for all projects
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        $project = $this->projects->find($project->id);
        return $project && $project->pivot->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user has access to a specific project
     */
    public function hasAccessToProject(Project $project): bool
    {
        // Super admins have access to all projects
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return $this->projects->contains($project);
    }
    
    /**
     * Check if user is admin for any project (including super admin)
     */
    public function isAdminForAnyProject(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return $this->projects()->wherePivot('role', self::ROLE_ADMIN)->exists();
    }
    
    /**
     * Get role for a specific project
     */
    public function getRoleForProject(Project $project): ?string
    {
        // Super admins are always admin for all projects
        if ($this->isSuperAdmin()) {
            return self::ROLE_ADMIN;
        }
        
        $project = $this->projects->find($project->id);
        return $project ? $project->pivot->role : null;
    }
}
