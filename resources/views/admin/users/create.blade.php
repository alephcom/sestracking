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
                <div class="form-check">
                    <input type="checkbox" name="super_admin" id="super_admin" value="1" 
                           class="form-check-input @error('super_admin') is-invalid @enderror"
                           {{ old('super_admin') ? 'checked' : '' }}>
                    <label for="super_admin" class="form-check-label">
                        Super Admin (has admin access to all projects)
                    </label>
                    @error('super_admin')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="form-text text-muted d-block">
                        Super admins have admin access to all projects automatically and don't need project assignments.
                    </small>
                </div>
            </div>

            <div class="form-group mb-3" id="projects-section">
                <label>Assign Projects & Roles</label>
                <div class="row">
                    @foreach($projects as $project)
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="projects[]" value="{{ $project->id }}" 
                                       id="project_{{ $project->id }}" class="form-check-input project-checkbox"
                                       {{ in_array($project->id, old('projects', [])) ? 'checked' : '' }}>
                                <label for="project_{{ $project->id }}" class="form-check-label">
                                    {{ $project->name }}
                                </label>
                            </div>
                            <select name="project_roles[{{ $project->id }}]" 
                                    id="role_{{ $project->id }}" 
                                    class="form-select form-select-sm mt-1 project-role-select"
                                    disabled>
                                <option value="user" {{ old("project_roles.{$project->id}", 'user') === 'user' ? 'selected' : '' }}>User</option>
                                <option value="admin" {{ old("project_roles.{$project->id}", 'user') === 'admin' ? 'selected' : '' }}>Admin</option>
                            </select>
                        </div>
                    @endforeach
                </div>
                @if($projects->count() == 0)
                    <p class="text-muted">No projects available.</p>
                @endif
                <small class="form-text text-muted">
                    Select projects and assign roles. Users can be admin for some projects and regular users for others. (Not applicable for super admins)
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
document.addEventListener('DOMContentLoaded', function() {
    const superAdminCheckbox = document.getElementById('super_admin');
    const projectsSection = document.getElementById('projects-section');
    
    // Handle super admin checkbox change
    superAdminCheckbox.addEventListener('change', function() {
        if (this.checked) {
            projectsSection.style.display = 'none';
            // Uncheck all projects
            document.querySelectorAll('.project-checkbox').forEach(cb => cb.checked = false);
        } else {
            projectsSection.style.display = 'block';
        }
    });
    
    // Initialize on page load
    if (superAdminCheckbox.checked) {
        projectsSection.style.display = 'none';
    }
    
    // Handle project checkbox changes
    const checkboxes = document.querySelectorAll('.project-checkbox');
    checkboxes.forEach(function(checkbox) {
        const projectId = checkbox.value;
        const roleSelect = document.getElementById('role_' + projectId);
        
        // Enable/disable role select based on checkbox
        checkbox.addEventListener('change', function() {
            roleSelect.disabled = !this.checked;
            if (!this.checked) {
                roleSelect.value = 'user'; // Reset to default
            }
        });
        
        // Initialize on page load
        roleSelect.disabled = !checkbox.checked;
    });
});
</script>

@endsection
