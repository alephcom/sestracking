@extends('layouts.master')

@section('site-title')
    Project Requests
@endsection

@section('h1')
    <h1 class="h2">Project Requests</h1>
@endsection

@section('page-content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">Project Requests</h3>
        <p class="text-muted mb-0">Review and approve project requests from users</p>
    </div>
</div>

@if($requests->count() > 0)
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Project Name</th>
                            <th>Requested By</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $request)
                        <tr>
                            <td><strong>#{{ $request->id }}</strong></td>
                            <td>
                                <strong>{{ $request->name }}</strong>
                            </td>
                            <td>
                                <div>
                                    <strong>{{ $request->requester->name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $request->requester->email }}</small>
                                </div>
                            </td>
                            <td>
                                @if($request->description)
                                    <span class="text-muted">{{ Str::limit($request->description, 50) }}</span>
                                @else
                                    <span class="text-muted small">No description</span>
                                @endif
                            </td>
                            <td>
                                @if($request->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @elseif($request->status === 'approved')
                                    <span class="badge bg-success">Approved</span>
                                @else
                                    <span class="badge bg-danger">Rejected</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted">{{ $request->created_at->format('M d, Y H:i') }}</span>
                            </td>
                            <td>
                                @if($request->status === 'pending')
                                    <a href="{{ route('project-requests.show', $request) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                @else
                                    <span class="text-muted small">Processed</span>
                                    @if($request->project)
                                        <br>
                                        <a href="{{ route('admin.projects.edit', $request->project) }}" class="btn btn-sm btn-outline-secondary mt-1">
                                            <i class="fas fa-edit"></i> View Project
                                        </a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $requests->links() }}
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No project requests found</h4>
            <p class="text-muted">All requests have been processed or no requests have been submitted yet.</p>
        </div>
    </div>
@endif

@endsection

