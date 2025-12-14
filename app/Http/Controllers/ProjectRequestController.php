<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectRequestController extends Controller
{
    /**
     * Show the form for creating a new project request
     */
    public function create()
    {
        return view('project-requests.create');
    }

    /**
     * Store a new project request
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        ProjectRequest::create([
            'requested_by' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return redirect()->route('project-requests.create')
            ->with('success', 'Project request submitted successfully! A super admin will review your request.');
    }

    /**
     * Display a listing of project requests (for super admins)
     */
    public function index()
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Only super admins can view project requests.');
        }

        $requests = ProjectRequest::with(['requester', 'approver', 'project'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('project-requests.index', compact('requests'));
    }

    /**
     * Approve a project request
     */
    public function approve(Request $request, ProjectRequest $projectRequest)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Only super admins can approve project requests.');
        }

        if ($projectRequest->status !== 'pending') {
            return redirect()->route('project-requests.index')
                ->with('error', 'This request has already been processed.');
        }

        $request->validate([
            'users' => 'array',
            'users.*' => 'exists:users,id',
            'admins' => 'array',
            'admins.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($projectRequest, $request) {
            // Create the project
            $project = Project::create([
                'name' => $projectRequest->name,
                'token' => generateToken(),
            ]);

            // Assign users to project with roles
            $usersToAttach = [];
            
            // Add requester as admin by default
            $usersToAttach[$projectRequest->requested_by] = ['role' => 'admin'];
            
            // Add regular users
            if ($request->has('users')) {
                foreach ($request->users as $userId) {
                    if (!isset($usersToAttach[$userId])) {
                        $usersToAttach[$userId] = ['role' => 'user'];
                    }
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

            // Update the project request
            $projectRequest->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'project_id' => $project->id,
                'approved_at' => now(),
            ]);
        });

        return redirect()->route('project-requests.index')
            ->with('success', 'Project request approved and project created successfully!');
    }

    /**
     * Show the approval form for a project request
     */
    public function show(ProjectRequest $projectRequest)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Only super admins can view project request details.');
        }

        $projectRequest->load(['requester']);

        // Make search-users route available in the view
        $searchUsersRoute = route('admin.projects.search-users');

        return view('project-requests.show', compact('projectRequest', 'searchUsersRoute'));
    }

    /**
     * Reject a project request
     */
    public function reject(Request $request, ProjectRequest $projectRequest)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Only super admins can reject project requests.');
        }

        if ($projectRequest->status !== 'pending') {
            return redirect()->route('project-requests.index')
                ->with('error', 'This request has already been processed.');
        }

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $projectRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'rejection_reason' => $request->rejection_reason,
            'rejected_at' => now(),
        ]);

        return redirect()->route('project-requests.index')
            ->with('success', 'Project request rejected.');
    }
}
