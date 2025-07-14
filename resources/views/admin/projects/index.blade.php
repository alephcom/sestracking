@extends('layouts.master')

@section('site-title')
    Project Management
@endsection

@section('h1')
    <h1 class="h2">Project Management</h1>
@endsection

@section('page-content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>All Projects</h3>
    <a href="{{ route('admin.projects.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create New Project
    </a>
</div>

@if($projects->count() > 0)
    <div class="table-responsive">
        <table class="table table-striped">
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
                    <td>{{ $project->id }}</td>
                    <td>{{ $project->name }}</td>
                    <td>
                        <code class="small">{{ Str::limit($project->token, 20) }}...</code>
                    </td>
                    <td>
                        @if($project->users->count() > 0)
                            @foreach($project->users as $user)
                                <span class="badge badge-info">{{ $user->name }}</span>
                            @endforeach
                        @else
                            <span class="text-muted">No users assigned</span>
                        @endif
                    </td>
                    <td>{{ $project->created_at->format('M d, Y') }}</td>
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
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $projects->links() }}
@else
    <div class="alert alert-info">
        No projects found. <a href="{{ route('admin.projects.create') }}">Create the first project</a>.
    </div>
@endif

@endsection