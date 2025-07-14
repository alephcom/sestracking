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
        
        $users = User::where('role', User::ROLE_USER)->orderBy('name')->get();
        return view('admin.projects.create', compact('users'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'users' => 'array',
            'users.*' => 'exists:users,id'
        ]);

        $project = Project::create([
            'name' => $request->name,
            'token' => generateToken(),
        ]);

        // Assign users to project
        if ($request->has('users')) {
            $project->users()->attach($request->users);
        }

        return redirect()->route('admin.projects.index')
            ->with('success', 'Project created successfully!');
    }

    public function edit(Project $project)
    {
        $this->authorize('update', $project);
        
        $users = User::where('role', User::ROLE_USER)->orderBy('name')->get();
        $assignedUsers = $project->users->pluck('id')->toArray();
        
        return view('admin.projects.edit', compact('project', 'users', 'assignedUsers'));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'users' => 'array',
            'users.*' => 'exists:users,id'
        ]);

        $project->update([
            'name' => $request->name,
        ]);

        // Update user assignments
        $project->users()->sync($request->users ?? []);

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