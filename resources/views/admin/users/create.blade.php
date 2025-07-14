@extends('layouts.master')

@section('site-title')
    Create User
@endsection

@section('h1')
    <h1 class="h2">Create User</h1>
@endsection

@section('page-content')

<div class="row">
    <div class="col-md-8">
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            
            <div class="form-group mb-3">
                <label for="name">Name *</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                       value="{{ old('name') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="email">Email *</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" 
                       value="{{ old('email') }}" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="password">Password *</label>
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="password_confirmation">Confirm Password *</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
            </div>

            <div class="form-group mb-3">
                <label for="role">Role *</label>
                <select name="role" id="role" class="form-control @error('role') is-invalid @enderror" required>
                    <option value="">Select Role</option>
                    <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>User</option>
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
                @error('role')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3" id="projects-section">
                <label>Assign Projects</label>
                <div class="row">
                    @foreach($projects as $project)
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="projects[]" value="{{ $project->id }}" 
                                       id="project_{{ $project->id }}" class="form-check-input"
                                       {{ in_array($project->id, old('projects', [])) ? 'checked' : '' }}>
                                <label for="project_{{ $project->id }}" class="form-check-label">
                                    {{ $project->name }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($projects->count() == 0)
                    <p class="text-muted">No projects available.</p>
                @endif
                <small class="form-text text-muted">
                    Note: Admin users automatically have access to all projects.
                </small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create User
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('role').addEventListener('change', function() {
    const projectsSection = document.getElementById('projects-section');
    if (this.value === 'admin') {
        projectsSection.style.display = 'none';
        // Uncheck all project checkboxes for admins
        const checkboxes = document.querySelectorAll('input[name="projects[]"]');
        checkboxes.forEach(cb => cb.checked = false);
    } else {
        projectsSection.style.display = 'block';
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    if (roleSelect.value === 'admin') {
        document.getElementById('projects-section').style.display = 'none';
    }
});
</script>

@endsection