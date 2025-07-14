@extends('layouts.master')

@section('site-title')
    Create Project
@endsection

@section('h1')
    <h1 class="h2">Create New Project</h1>
@endsection

@section('page-content')

<div class="row">
    <div class="col-md-8">
        <form action="{{ route('admin.projects.store') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="name">Project Name *</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                       value="{{ old('name') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Assign Users to Project</label>
                <div class="row">
                    @foreach($users as $user)
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="users[]" value="{{ $user->id }}" 
                                       id="user_{{ $user->id }}" class="form-check-input"
                                       {{ in_array($user->id, old('users', [])) ? 'checked' : '' }}>
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
                    <i class="fas fa-save"></i> Create Project
                </button>
                <a href="{{ route('admin.projects.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Projects
                </a>
            </div>
        </form>
    </div>
</div>

@endsection