@extends('layouts.master')


@section('site-title')
    Dashboard
@endsection

@section('h1')
    <h1 class="h2">Dashboard</h1>
@endsection

@section('page-content')

  @if($accessibleProjects->isNotEmpty())
    <!-- Project Selector -->
    <div class="row mb-3">
      <div class="col-md-4">
        <div class="form-group">
          <label for="project_selector">Select Project:</label>
          <select id="project_selector" class="form-control">
            <option value="all">All Projects</option>
            @foreach($accessibleProjects as $project)
              <option value="{{ $project->id }}">{{ $project->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>
    
    <div id="app"></div>
  @else
    <div class="alert alert-info">No projects available. Contact an administrator to get access to projects.</div>
  @endif

@endsection

@section('scripts')
  <script>
    window.dashboardEndpoint = '{{ route('dashboard.api') }}';
    window.dashboardProjectId = 'all';
    window.accessibleProjects = @json($accessibleProjects->pluck('id')->toArray());
    
    // Project selector functionality
    document.addEventListener('DOMContentLoaded', function() {
      const projectSelector = document.getElementById('project_selector');
      if (projectSelector) {
        projectSelector.addEventListener('change', function() {
          window.dashboardProjectId = this.value;
          // Trigger Vue component to reload data
          if (window.dashboardVueInstance && window.dashboardVueInstance.loadData) {
            window.dashboardVueInstance.projectId = this.value;
            window.dashboardVueInstance.loadData();
          }
        });
      }
    });
  </script>
  <script src="{{ mix('js/dashboard.js') }}"></script>
@endsection