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
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'projects' => 'array',
            'projects.*' => 'exists:projects,id'
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            // Attach projects if user is not admin (admins have access to all projects)
            if ($request->role === User::ROLE_USER && $request->projects) {
                $user->projects()->attach($request->projects);
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
        
        return view('admin.users.edit', compact('user', 'projects', 'assignedProjects'));
    }

    public function update(Request $request, User $user)
    {
        // Protect user ID=1 from role changes
        $roleValidation = $user->id === 1 
            ? ['required', Rule::in([User::ROLE_ADMIN])] 
            : ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])];

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => $roleValidation,
            'projects' => 'array',
            'projects.*' => 'exists:projects,id'
        ]);

        DB::transaction(function () use ($request, $user) {
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            // Update project assignments
            if ($request->role === User::ROLE_USER) {
                // Regular users get assigned projects
                $user->projects()->sync($request->projects ?? []);
            } else {
                // Admins don't need project assignments (they have access to all)
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