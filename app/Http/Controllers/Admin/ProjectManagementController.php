<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectManagementController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Project::class);
        
        $projects = Project::with('users')->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.projects.index', compact('projects'));
    }

    public function create()
    {
        $this->authorize('create', Project::class);
        
        return view('admin.projects.create');
    }
    
    public function searchUsers(Request $request)
    {
        // Allow search if user can view any projects (admin access)
        $this->authorize('viewAny', Project::class);
        
        $request->validate([
            'query' => 'required|string|min:8',
        ]);
        
        $query = $request->get('query');
        
        // Search users by email starting with the query
        $users = User::where('email', 'like', $query . '%')
            ->orderBy('email')
            ->limit(50)
            ->get(['id', 'name', 'email']);
        
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'users' => 'array',
            'users.*' => 'exists:users,id',
            'admins' => 'array',
            'admins.*' => 'exists:users,id',
        ]);

        $project = Project::create([
            'name' => $request->name,
            'token' => generateToken(),
        ]);

        // Assign users to project with roles
        $usersToAttach = [];
        
        // Add regular users
        if ($request->has('users')) {
            foreach ($request->users as $userId) {
                $usersToAttach[$userId] = ['role' => 'user'];
            }
        }
        
        // Add admins
        if ($request->has('admins')) {
            foreach ($request->admins as $userId) {
                $usersToAttach[$userId] = ['role' => 'admin'];
            }
        }
        
        if (!empty($usersToAttach)) {
            $project->users()->attach($usersToAttach);
        }

        return redirect()->route('admin.projects.index')
            ->with('success', 'Project created successfully!');
    }

    public function edit(Project $project)
    {
        $this->authorize('update', $project);
        
        // Get assigned users and admins
        $assignedUsers = $project->users()->wherePivot('role', 'user')->pluck('users.id')->toArray();
        $assignedAdmins = $project->users()->wherePivot('role', 'admin')->pluck('users.id')->toArray();
        
        // Get user details for display
        $userDetails = $project->users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        })->keyBy('id')->toArray();
        
        return view('admin.projects.edit', compact('project', 'assignedUsers', 'assignedAdmins', 'userDetails'));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'users' => 'array',
            'users.*' => 'exists:users,id',
            'admins' => 'array',
            'admins.*' => 'exists:users,id',
        ]);

        $project->update([
            'name' => $request->name,
        ]);

        // Update user assignments with roles
        $usersToSync = [];
        
        // Add regular users
        if ($request->has('users')) {
            foreach ($request->users as $userId) {
                $usersToSync[$userId] = ['role' => 'user'];
            }
        }
        
        // Add admins
        if ($request->has('admins')) {
            foreach ($request->admins as $userId) {
                $usersToSync[$userId] = ['role' => 'admin'];
            }
        }
        
        $project->users()->sync($usersToSync);

        return redirect()->route('admin.projects.index')
            ->with('success', 'Project updated successfully!');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        
        $project->delete();
        
        return redirect()->route('admin.projects.index')
            ->with('success', 'Project deleted successfully!');
    }

}