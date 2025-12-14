@extends('layouts.master')

@section('site-title')
    Request Project
@endsection

@section('h1')
    <h1 class="h2">Request a New Project</h1>
@endsection

@section('page-content')

<div class="row">
    <div class="col-md-8">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form action="{{ route('project-requests.store') }}" method="POST">
            @csrf
            
            <div class="form-group mb-3">
                <label for="name">Project Name *</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                       value="{{ old('name') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="description">Description (Optional)</label>
                <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" 
                          rows="4" maxlength="1000">{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Provide additional details about the project you're requesting (max 1000 characters).</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </form>

        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle"></i> 
            <strong>Note:</strong> Your request will be reviewed by a super admin. You'll be notified once it's approved or rejected.
        </div>
    </div>
</div>

@endsection

