@extends('layouts.master')


@section('site-title')
    Activity
@endsection

@section('h1')
    <h1 class="h2">Activity</h1>
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
        
        <!-- Tracking Notice -->
        <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle"></i>
            <strong>Note:</strong> Open tracking can be blocked by email providers or adblockers, therefore having the number of clicks larger than the number of opens is completely normal.
        </div>
        
        <div id="app"></div>
    @else
        <div class="alert alert-info">No projects available. Contact an administrator to get access to projects.</div>
    @endif

@endsection

@section('scripts')
  <script>
    window.dashboardProjectId = 'all';
    window.accessibleProjects = @json($accessibleProjects->pluck('id')->toArray());
    window.APP_EXPORT_URL = '{{ url('activity/export')}}';
    
    // Project selector functionality for activity
    document.addEventListener('DOMContentLoaded', function() {
      const projectSelector = document.getElementById('project_selector');
      if (projectSelector) {
        projectSelector.addEventListener('change', function() {
          window.dashboardProjectId = this.value;
          // Trigger Vue component to reload data
          if (window.activityVueInstance) {
            window.activityVueInstance.updateProjectId(this.value);
            window.activityVueInstance.currentPage = 1; // Reset to first page
            window.activityVueInstance.loadData();
          }
        });
      }
    });
  </script>
  <script src="{{ mix('js/activity.js') }}"></script>

@endsection