@extends('layouts.master')

@section('site-title')
    Edit Project
@endsection

@section('h1')
    <h1 class="h2">Edit Project: {{ $project->name }}</h1>
@endsection

@section('page-content')

<div class="row">
    <div class="col-md-8">
        <form action="{{ route('admin.projects.update', $project) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="form-group">
                <label for="name">Project Name *</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                       value="{{ old('name', $project->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Project Token</label>
                <div class="input-group">
                    <input type="text" class="form-control" value="{{ $project->token }}" readonly>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $project->token }}')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted">This token is used for webhook authentication.</small>
            </div>

            <div class="form-group">
                <label>Assign Users to Project</label>
                <div class="row">
                    @foreach($users as $user)
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="users[]" value="{{ $user->id }}" 
                                       id="user_{{ $user->id }}" class="form-check-input"
                                       {{ in_array($user->id, old('users', $assignedUsers)) ? 'checked' : '' }}>
                                <label for="user_{{ $user->id }}" class="form-check-label">
                                    {{ $user->name }} ({{ $user->email }})
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($users->count() == 0)
                    <p class="text-muted">No regular users available. All users are admins.</p>
                @endif
                <small class="form-text text-muted">
                    Note: Admin users have access to all projects automatically.
                </small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Project
                </button>
                <a href="{{ route('admin.projects.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Projects
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Webhook Configuration Section -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-webhook"></i> WebHook Configuration</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Webhook URL</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="{{ url('/webhook/' . $project->token) }}" readonly>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('{{ url('/webhook/' . $project->token) }}')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted">Use this URL to configure AWS SES webhook notifications.</small>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-cogs"></i> Configure AWS Simple Email Service</h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Follow Configuration instructions:
                    <a href="https://sesdashboard.readthedocs.io/en/latest/configuration.html" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt"></i> Configuration Guide
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Token copied to clipboard!');
    });
}
</script>

@endsection