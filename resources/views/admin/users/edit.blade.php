@extends('layouts.master')

@section('site-title')
    Edit User
@endsection

@section('h1')
    <h1 class="h2">Edit User: {{ $user->name }}</h1>
@endsection

@section('page-content')

<div class="row">
    <div class="col-md-8">
        <form action="{{ route('admin.users.update', $user) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="form-group mb-3">
                <label for="name">Name *</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                       value="{{ old('name', $user->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="email">Email *</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" 
                       value="{{ old('email', $user->email) }}" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror">
                <small class="form-text text-muted">Leave blank to keep current password.</small>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control">
            </div>

            <div class="form-group mb-3">
                <div class="form-check">
                    <input type="checkbox" name="super_admin" id="super_admin" value="1" 
                           class="form-check-input @error('super_admin') is-invalid @enderror"
                           {{ old('super_admin', $user->super_admin) ? 'checked' : '' }}
                           {{ $user->id === 1 ? 'disabled' : '' }}>
                    <label for="super_admin" class="form-check-label">
                        Super Admin (has admin access to all projects)
                    </label>
                    @if($user->id === 1)
                        <input type="hidden" name="super_admin" value="{{ $user->super_admin ? '1' : '0' }}">
                        <small class="form-text text-muted d-block">Primary admin status cannot be changed.</small>
                    @endif
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
                        @php
                            $isAssigned = in_array($project->id, old('projects', $assignedProjects));
                            $currentRole = old("project_roles.{$project->id}", $projectRoles[$project->id] ?? 'user');
                        @endphp
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="projects[]" value="{{ $project->id }}" 
                                       id="project_{{ $project->id }}" class="form-check-input project-checkbox"
                                       {{ $isAssigned ? 'checked' : '' }}>
                                <label for="project_{{ $project->id }}" class="form-check-label">
                                    {{ $project->name }}
                                </label>
                            </div>
                            <select name="project_roles[{{ $project->id }}]" 
                                    id="role_{{ $project->id }}" 
                                    class="form-select form-select-sm mt-1 project-role-select"
                                    {{ !$isAssigned ? 'disabled' : '' }}>
                                <option value="user" {{ $currentRole === 'user' ? 'selected' : '' }}>User</option>
                                <option value="admin" {{ $currentRole === 'admin' ? 'selected' : '' }}>Admin</option>
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
                    <i class="fas fa-save"></i> Update User
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
