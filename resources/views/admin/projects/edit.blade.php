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
            
            <div class="form-group mb-3">
                <label for="name">Project Name *</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                       value="{{ old('name', $project->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
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

            <div class="form-group mb-3">
                <label>Assign Users to Project</label>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="user-search" class="form-label">Search Users (enter first 8 characters of email)</label>
                            <input type="text" id="user-search" class="form-control" placeholder="Enter at least 8 characters of email..." />
                            <div id="user-search-error" class="text-danger" style="display: none;">Please enter at least 8 characters to search.</div>
                        </div>
                        
                        <div id="user-search-results" style="display: none;">
                            <div class="list-group mb-2" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>

                        <label for="selected-users" class="form-label">Selected Users (Regular)</label>
                        <select name="users[]" id="selected-users" multiple class="form-select" size="8">
                            @foreach($assignedUsers as $userId)
                                @if(isset($userDetails[$userId]))
                                    <option value="{{ $userId }}" selected>
                                        {{ $userDetails[$userId]['name'] }} ({{ $userDetails[$userId]['email'] }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-danger mt-2" id="remove-user">Remove Selected User</button>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="admin-search" class="form-label">Search Admins (enter first 8 characters of email)</label>
                            <input type="text" id="admin-search" class="form-control" placeholder="Enter at least 8 characters of email..." />
                            <div id="admin-search-error" class="text-danger" style="display: none;">Please enter at least 8 characters to search.</div>
                        </div>
                        
                        <div id="admin-search-results" style="display: none;">
                            <div class="list-group mb-2" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>

                        <label for="selected-admins" class="form-label">Selected Admins</label>
                        <select name="admins[]" id="selected-admins" multiple class="form-select" size="8">
                            @foreach($assignedAdmins as $userId)
                                @if(isset($userDetails[$userId]))
                                    <option value="{{ $userId }}" selected>
                                        {{ $userDetails[$userId]['name'] }} ({{ $userDetails[$userId]['email'] }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-warning" id="move-admin-to-users">Move to Users</button>
                            <button type="button" class="btn btn-sm btn-danger" id="remove-admin">Remove Admin</button>
                        </div>
                    </div>
                </div>
                
                <small class="form-text text-muted">
                    Enter the first 8 characters of a user's email address to search. Use the left search box to add regular users, and the right search box to add admins.
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

document.addEventListener('DOMContentLoaded', function() {
    const userSearchInput = document.getElementById('user-search');
    const userSearchError = document.getElementById('user-search-error');
    const userSearchResults = document.getElementById('user-search-results');
    const userSearchResultsList = userSearchResults.querySelector('.list-group');
    
    const adminSearchInput = document.getElementById('admin-search');
    const adminSearchError = document.getElementById('admin-search-error');
    const adminSearchResults = document.getElementById('admin-search-results');
    const adminSearchResultsList = adminSearchResults.querySelector('.list-group');
    
    const selectedUsers = document.getElementById('selected-users');
    const selectedAdmins = document.getElementById('selected-admins');
    let userSearchTimeout = null;
    let adminSearchTimeout = null;
    let allSelectedUsers = new Set(); // Track all selected user IDs

    // Initialize allSelectedUsers with existing selections
    Array.from(selectedUsers.options).forEach(option => {
        allSelectedUsers.add(parseInt(option.value));
    });
    Array.from(selectedAdmins.options).forEach(option => {
        allSelectedUsers.add(parseInt(option.value));
    });

    // User search functionality
    userSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 8) {
            userSearchResults.style.display = 'none';
            userSearchError.style.display = query.length > 0 ? 'block' : 'none';
            return;
        }
        
        userSearchError.style.display = 'none';
        
        // Debounce search
        clearTimeout(userSearchTimeout);
        userSearchTimeout = setTimeout(() => {
            searchUsers(query, userSearchResultsList, 'user');
        }, 300);
    });

    // Admin search functionality
    adminSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 8) {
            adminSearchResults.style.display = 'none';
            adminSearchError.style.display = query.length > 0 ? 'block' : 'none';
            return;
        }
        
        adminSearchError.style.display = 'none';
        
        // Debounce search
        clearTimeout(adminSearchTimeout);
        adminSearchTimeout = setTimeout(() => {
            searchUsers(query, adminSearchResultsList, 'admin');
        }, 300);
    });

    function searchUsers(query, resultsList, targetList) {
        fetch('{{ route("admin.projects.search-users") }}?query=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(users => {
                resultsList.innerHTML = '';
                
                if (users.length === 0) {
                    resultsList.innerHTML = '<div class="list-group-item text-muted">No users found</div>';
                } else {
                    users.forEach(user => {
                        // Skip if already selected
                        if (allSelectedUsers.has(user.id)) {
                            return;
                        }
                        
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `<strong>${user.name}</strong> (${user.email})`;
                        item.dataset.userId = user.id;
                        item.dataset.userName = user.name;
                        item.dataset.userEmail = user.email;
                        item.addEventListener('click', function() {
                            addUserToList(user.id, user.name, user.email, targetList);
                        });
                        resultsList.appendChild(item);
                    });
                }
                
                if (targetList === 'user') {
                    userSearchResults.style.display = 'block';
                } else {
                    adminSearchResults.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    }

    function addUserToList(userId, userName, userEmail, targetList) {
        if (allSelectedUsers.has(userId)) {
            return; // Already added
        }
        
        allSelectedUsers.add(userId);
        
        const option = document.createElement('option');
        option.value = userId;
        option.textContent = `${userName} (${userEmail})`;
        option.dataset.userName = userName;
        option.dataset.userEmail = userEmail;
        
        if (targetList === 'user') {
            selectedUsers.appendChild(option);
            // Clear user search
            userSearchInput.value = '';
            userSearchResults.style.display = 'none';
        } else {
            selectedAdmins.appendChild(option);
            // Clear admin search
            adminSearchInput.value = '';
            adminSearchResults.style.display = 'none';
        }
    }

    // Move admin back to user list
    document.getElementById('move-admin-to-users').addEventListener('click', function() {
        const selected = selectedAdmins.selectedOptions;
        if (selected.length === 0) return;
        
        Array.from(selected).forEach(option => {
            const optionClone = option.cloneNode(true);
            selectedUsers.appendChild(optionClone);
            option.remove();
        });
    });

    // Remove user completely
    document.getElementById('remove-user').addEventListener('click', function() {
        const selected = selectedUsers.selectedOptions;
        if (selected.length === 0) return;
        
        Array.from(selected).forEach(option => {
            allSelectedUsers.delete(parseInt(option.value));
            option.remove();
        });
    });
    
    // Remove admin completely
    document.getElementById('remove-admin').addEventListener('click', function() {
        const selected = selectedAdmins.selectedOptions;
        if (selected.length === 0) return;
        
        Array.from(selected).forEach(option => {
            allSelectedUsers.delete(parseInt(option.value));
            option.remove();
        });
    });
});
</script>

@endsection
