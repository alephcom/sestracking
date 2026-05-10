<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $users = User::where('email', 'like', $query.'%')
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

        if (! empty($usersToAttach)) {
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
            'ses_aws_access_key_id' => 'nullable|string|max:255',
            'ses_aws_secret_access_key' => 'nullable|string|max:8192',
            'ses_aws_default_region' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        $input = $request->all();

        $key = $project->ses_aws_access_key_id;
        if (array_key_exists('ses_aws_access_key_id', $input)) {
            $keyRaw = $request->input('ses_aws_access_key_id');
            $key = is_string($keyRaw) && trim($keyRaw) !== '' ? trim($keyRaw) : null;
        }

        $secretPresent = $request->filled('ses_aws_secret_access_key');

        if ($key && ! $secretPresent && ! $project->ses_aws_secret_access_key) {
            throw ValidationException::withMessages([
                'ses_aws_secret_access_key' => 'Secret access key is required when Access Key ID is set. Clear both fields to disable per-project suppression credentials.',
            ]);
        }

        if (! $key && $secretPresent) {
            throw ValidationException::withMessages([
                'ses_aws_access_key_id' => 'Access Key ID is required when entering a new secret access key.',
            ]);
        }

        $region = $project->ses_aws_default_region;
        if (array_key_exists('ses_aws_default_region', $input)) {
            $regionRaw = $request->input('ses_aws_default_region');
            $region = is_string($regionRaw) && trim($regionRaw) !== '' ? trim($regionRaw) : null;
        }

        $update = [
            'name' => $request->name,
            'ses_suppression_auto_push_enabled' => $request->boolean('ses_suppression_auto_push_enabled'),
            'ses_suppression_push_complaints' => $request->boolean('ses_suppression_push_complaints'),
            'ses_suppression_push_soft_bounces' => $request->boolean('ses_suppression_push_soft_bounces'),
            'ses_aws_access_key_id' => $key,
            'ses_aws_default_region' => $region,
        ];

        if ($secretPresent) {
            $update['ses_aws_secret_access_key'] = (string) $request->input('ses_aws_secret_access_key');
        }

        if ($key === null) {
            $update['ses_aws_secret_access_key'] = null;
        }

        $project->update($update);

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
