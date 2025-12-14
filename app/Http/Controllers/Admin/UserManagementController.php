<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with('projects')->orderBy('id')->get();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $projects = Project::all();
        return view('admin.users.create', compact('projects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'super_admin' => 'boolean',
            'projects' => 'array',
            'projects.*' => 'exists:projects,id',
            'project_roles' => 'array',
            'project_roles.*' => ['required', 'in:admin,user']
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'super_admin' => $request->has('super_admin'),
            ]);

            // Attach projects with roles (only if not super admin)
            if (!$user->isSuperAdmin() && $request->has('projects')) {
                $projectsToAttach = [];
                foreach ($request->projects as $projectId) {
                    $role = $request->project_roles[$projectId] ?? 'user';
                    $projectsToAttach[$projectId] = ['role' => $role];
                }
                $user->projects()->attach($projectsToAttach);
            }
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $user->load('projects');
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $projects = Project::all();
        $assignedProjects = $user->projects->pluck('id')->toArray();
        $projectRoles = $user->projects->mapWithKeys(function ($project) {
            return [$project->id => $project->pivot->role];
        })->toArray();
        
        return view('admin.users.edit', compact('user', 'projects', 'assignedProjects', 'projectRoles'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'super_admin' => 'boolean',
            'projects' => 'array',
            'projects.*' => 'exists:projects,id',
            'project_roles' => 'array',
            'project_roles.*' => ['required', 'in:admin,user']
        ]);

        DB::transaction(function () use ($request, $user) {
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'super_admin' => $request->has('super_admin'),
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            // Update project assignments with roles (only if not super admin)
            if (!$user->isSuperAdmin()) {
                $projectsToSync = [];
                if ($request->has('projects')) {
                    foreach ($request->projects as $projectId) {
                        $role = $request->project_roles[$projectId] ?? 'user';
                        $projectsToSync[$projectId] = ['role' => $role];
                    }
                }
                $user->projects()->sync($projectsToSync);
            } else {
                // Super admins don't need project assignments
                $user->projects()->detach();
            }
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        // Protect user ID=1 from deletion
        if ($user->id === 1) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot delete the primary admin user.');
        }

        DB::transaction(function () use ($user) {
            // Remove user from all projects
            $user->projects()->detach();
            
            // Delete the user
            $user->delete();
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}