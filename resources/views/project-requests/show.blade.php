@extends('layouts.master')

@section('site-title')
    Review Project Request
@endsection

@section('h1')
    <h1 class="h2">Review Project Request</h1>
@endsection

@section('page-content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5>Request Details</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Project Name:</dt>
                    <dd class="col-sm-9">{{ $projectRequest->name }}</dd>

                    <dt class="col-sm-3">Requested By:</dt>
                    <dd class="col-sm-9">{{ $projectRequest->requester->name }} ({{ $projectRequest->requester->email }})</dd>

                    <dt class="col-sm-3">Description:</dt>
                    <dd class="col-sm-9">
                        @if($projectRequest->description)
                            {{ $projectRequest->description }}
                        @else
                            <span class="text-muted">No description provided</span>
                        @endif
                    </dd>

                    <dt class="col-sm-3">Status:</dt>
                    <dd class="col-sm-9">
                        <span class="badge bg-warning">Pending</span>
                    </dd>

                    <dt class="col-sm-3">Requested:</dt>
                    <dd class="col-sm-9">{{ $projectRequest->created_at->format('M d, Y H:i') }}</dd>
                </dl>
            </div>
        </div>

        <form action="{{ route('project-requests.approve', $projectRequest) }}" method="POST" id="approve-form">
            @csrf
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Assign Users to Project</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        The requester ({{ $projectRequest->requester->name }}) will be automatically added as an admin.
                    </div>

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
                            </select>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-warning" id="move-admin-to-users">Move to Users</button>
                                <button type="button" class="btn btn-sm btn-danger" id="remove-admin">Remove Admin</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Approve & Create Project
                </button>
                <a href="{{ route('project-requests.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Requests
                </a>
            </div>
        </form>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Reject Request</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('project-requests.reject', $projectRequest) }}" method="POST" onsubmit="return confirm('Are you sure you want to reject this project request?')">
                    @csrf
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Rejection Reason (Optional)</label>
                        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" maxlength="500"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Reject Request
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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

    // Exclude the requester from selection since they're automatically added as admin
    allSelectedUsers.add({{ $projectRequest->requester->id }});

    // User search functionality
    userSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 8) {
            userSearchResults.style.display = 'none';
            userSearchError.style.display = query.length > 0 ? 'block' : 'none';
            return;
        }
        
        userSearchError.style.display = 'none';
        
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
        
        clearTimeout(adminSearchTimeout);
        adminSearchTimeout = setTimeout(() => {
            searchUsers(query, adminSearchResultsList, 'admin');
        }, 300);
    });

    function searchUsers(query, resultsList, targetList) {
        fetch('{{ $searchUsersRoute }}?query=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(users => {
                resultsList.innerHTML = '';
                
                if (users.length === 0) {
                    resultsList.innerHTML = '<div class="list-group-item text-muted">No users found</div>';
                } else {
                    users.forEach(user => {
                        // Skip if already selected or is the requester
                        if (allSelectedUsers.has(user.id) || user.id === {{ $projectRequest->requester->id }}) {
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
            return;
        }
        
        allSelectedUsers.add(userId);
        
        const option = document.createElement('option');
        option.value = userId;
        option.textContent = `${userName} (${userEmail})`;
        option.dataset.userName = userName;
        option.dataset.userEmail = userEmail;
        
        if (targetList === 'user') {
            selectedUsers.appendChild(option);
            userSearchInput.value = '';
            userSearchResults.style.display = 'none';
        } else {
            selectedAdmins.appendChild(option);
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

