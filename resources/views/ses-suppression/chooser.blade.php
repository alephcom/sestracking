@extends('layouts.master')

@section('site-title')
    SES suppression lists
@endsection

@section('h1')
    <h1 class="h2">SES suppression lists</h1>
@endsection

@section('page-content')
    <p class="text-muted mb-4">Choose a project to view or manage addresses on the Amazon SES account-level suppression list for that project&rsquo;s AWS credentials and region.</p>

    @if($accessibleProjects->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="mb-0 text-muted">You do not have access to any projects yet.</p>
            </div>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accessibleProjects as $project)
                        <tr>
                            <td>{{ $project->name }}</td>
                            <td class="text-end">
                                <a href="{{ route('ses-suppression.index', $project) }}" class="btn btn-sm btn-primary">Open suppression list</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
