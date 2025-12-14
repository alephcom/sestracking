@extends('layouts.master')

@section('site-title')
    Manage Users
@endsection

@section('h1')
    <h1 class="h2">Manage Users</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-plus"></i> Add User
            </a>
        </div>
    </div>
@endsection

@section('page-content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Projects</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>
                        {{ $user->name }}
                        @if($user->id === 1)
                            <span class="badge bg-warning text-dark">Protected</span>
                        @endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @if($user->isSuperAdmin())
                            <span class="badge bg-dark">Super Admin</span>
                        @elseif($user->isAdminForAnyProject())
                            <span class="badge bg-danger">Admin (some projects)</span>
                        @else
                            <span class="badge bg-primary">User</span>
                        @endif
                    </td>
                    <td>
                        @if($user->isSuperAdmin())
                            <span class="text-muted">All Projects (Super Admin)</span>
                        @elseif($user->projects->count() > 0)
                            @foreach($user->projects as $project)
                                <span class="badge bg-{{ $project->pivot->role === 'admin' ? 'danger' : 'secondary' }} me-1">
                                    {{ $project->name }} ({{ $project->pivot->role }})
                                </span>
                            @endforeach
                        @else
                            <span class="text-muted">No Projects</span>
                        @endif
                    </td>
                    <td>{{ $user->created_at->format('M j, Y') }}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            @if($user->id !== 1)
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" 
                                      onsubmit="return confirm('Are you sure you want to delete this user? This will also remove them from all projects.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete primary admin">
                                    <i class="fas fa-trash"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No users found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection