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
    <div class="card mb-4">
      <div class="card-body">
        <div class="row align-items-end">
          <div class="col-md-8">
            <label for="project_selector" class="form-label">
              <i class="fas fa-filter me-2"></i>Select Projects to Include
            </label>
            <select id="project_selector" class="form-control" multiple size="6">
              <option value="all" selected>All Projects</option>
              @foreach($accessibleProjects as $project)
                <option value="{{ $project->id }}">{{ $project->name }}</option>
              @endforeach
            </select>
            <small class="form-text text-muted mt-2 d-block">
              <i class="fas fa-info-circle me-1"></i>
              Hold <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd> on Mac) to select multiple projects. "All Projects" will be automatically deselected if you select specific projects.
            </small>
          </div>
          <div class="col-md-4 mt-3 mt-md-0">
            <div id="selected-projects-count" class="text-muted small">
              <strong>Selected:</strong> All Projects
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div id="app"></div>
  @else
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
        <h4 class="text-muted">No projects available</h4>
        <p class="text-muted">Contact an administrator to get access to projects.</p>
        @if(!auth()->user()->isSuperAdmin())
        <a href="{{ route('project-requests.create') }}" class="btn btn-primary mt-2">
          <i class="fas fa-hand-paper"></i> Request a Project
        </a>
        @endif
      </div>
    </div>
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
      const selectedCountDisplay = document.getElementById('selected-projects-count');
      const projectNames = {
        @foreach($accessibleProjects as $project)
        '{{ $project->id }}': '{{ $project->name }}',
        @endforeach
      };
      
      function updateProjectSelection() {
        // Get all selected options
        const selectedOptions = Array.from(projectSelector.selectedOptions);
        let selectedValues = selectedOptions.map(option => option.value);
        
        // If nothing is selected, default to "all"
        if (selectedValues.length === 0) {
          const allOption = projectSelector.querySelector('option[value="all"]');
          if (allOption) {
            allOption.selected = true;
          }
          window.dashboardProjectId = 'all';
          if (selectedCountDisplay) {
            selectedCountDisplay.innerHTML = '<strong>Selected:</strong> All Projects';
          }
        }
        // If "all" is selected along with other options, remove "all"
        else if (selectedValues.includes('all') && selectedValues.length > 1) {
          selectedValues = selectedValues.filter(val => val !== 'all');
          // Update the select to remove "all" selection
          const allOption = projectSelector.querySelector('option[value="all"]');
          if (allOption) {
            allOption.selected = false;
          }
          window.dashboardProjectId = selectedValues.join(',');
          // Update display
          if (selectedCountDisplay) {
            const selectedNames = selectedValues.map(id => projectNames[id] || 'Project ' + id).join(', ');
            selectedCountDisplay.innerHTML = `<strong>Selected:</strong> ${selectedNames}`;
          }
        }
        // If only "all" is selected, use "all"
        else if (selectedValues.length === 1 && selectedValues[0] === 'all') {
          window.dashboardProjectId = 'all';
          if (selectedCountDisplay) {
            selectedCountDisplay.innerHTML = '<strong>Selected:</strong> All Projects';
          }
        } else {
          // Join multiple IDs with comma
          window.dashboardProjectId = selectedValues.join(',');
          // Update display
          if (selectedCountDisplay) {
            const selectedNames = selectedValues.map(id => projectNames[id] || 'Project ' + id).join(', ');
            selectedCountDisplay.innerHTML = `<strong>Selected:</strong> ${selectedNames}`;
          }
        }
        
        // Trigger dashboard app to reload data
        if (window.dashboardVueInstance && window.dashboardVueInstance.loadData) {
          window.dashboardVueInstance.projectId = window.dashboardProjectId;
          window.dashboardVueInstance.loadData();
        }
      }
      
      if (projectSelector) {
        projectSelector.addEventListener('change', updateProjectSelection);
        // Initial update
        updateProjectSelection();
      }
    });
  </script>
  <script src="{{ mix('js/dashboard.js') }}"></script>
@endsection