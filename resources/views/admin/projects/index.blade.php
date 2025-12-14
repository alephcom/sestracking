@extends('layouts.master')

@section('site-title')
    Project Management
@endsection

@section('h1')
    <h1 class="h2">Project Management</h1>
@endsection

@section('page-content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">All Projects</h3>
        <p class="text-muted mb-0">Manage your projects and user assignments</p>
    </div>
    <a href="{{ route('admin.projects.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create New Project
    </a>
</div>

@if($projects->count() > 0)
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Token</th>
                            <th>Assigned Users</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $project)
                        <tr>
                            <td><strong>#{{ $project->id }}</strong></td>
                            <td>
                                <strong>{{ $project->name }}</strong>
                            </td>
                            <td>
                                <code>{{ Str::limit($project->token, 20) }}...</code>
                            </td>
                            <td>
                                @if($project->users->count() > 0)
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($project->users->take(3) as $user)
                                            <span class="badge bg-info">{{ $user->name }}</span>
                                        @endforeach
                                        @if($project->users->count() > 3)
                                            <span class="badge bg-secondary">+{{ $project->users->count() - 3 }} more</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted small">No users assigned</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted">{{ $project->created_at->format('M d, Y') }}</span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.projects.edit', $project) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form action="{{ route('admin.projects.destroy', $project) }}" method="POST" class="d-inline" 
                                          onsubmit="return confirm('Are you sure you want to delete this project? This will also delete all associated emails and events.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $projects->links() }}
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No projects found</h4>
            <p class="text-muted mb-4">Get started by creating your first project</p>
            <a href="{{ route('admin.projects.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create the first project
            </a>
        </div>
    </div>
@endif

@endsection
