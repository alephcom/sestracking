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
    <div class="mb-4 d-flex align-items-center gap-3 flex-wrap">
      <div class="dropdown" id="project-dropdown-container">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="projectDropdownToggle"
                data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
          <i class="fas fa-filter me-2"></i><span id="project-dropdown-label">All Projects</span>
        </button>
        <ul class="dropdown-menu p-3" id="project-dropdown-menu" style="min-width: 240px;">
          <li>
            <div class="form-check mb-1">
              <input class="form-check-input" type="checkbox" value="all" id="proj-all" checked>
              <label class="form-check-label fw-semibold" for="proj-all">All Projects</label>
            </div>
          </li>
          <li><hr class="dropdown-divider my-2"></li>
          @foreach($accessibleProjects as $project)
          <li>
            <div class="form-check mb-1">
              <input class="form-check-input project-checkbox" type="checkbox"
                     value="{{ $project->id }}" id="proj-{{ $project->id }}">
              <label class="form-check-label" for="proj-{{ $project->id }}">{{ $project->name }}</label>
            </div>
          </li>
          @endforeach
        </ul>
      </div>
      <span id="selected-projects-summary" class="text-muted small"></span>
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

    document.addEventListener('DOMContentLoaded', function () {
      const allCheckbox   = document.getElementById('proj-all');
      const projectChecks = document.querySelectorAll('.project-checkbox');
      const dropdownLabel = document.getElementById('project-dropdown-label');
      const summary       = document.getElementById('selected-projects-summary');

      function updateSelection() {
        const checked = Array.from(projectChecks).filter(c => c.checked).map(c => c.value);

        if (checked.length === 0) {
          // Nothing selected — fall back to All
          allCheckbox.checked = true;
          window.dashboardProjectId = 'all';
          dropdownLabel.textContent = 'All Projects';
          if (summary) summary.textContent = '';
        } else if (allCheckbox.checked) {
          window.dashboardProjectId = 'all';
          dropdownLabel.textContent = 'All Projects';
          if (summary) summary.textContent = '';
        } else {
          window.dashboardProjectId = checked.join(',');
          const label = checked.length === 1
            ? document.querySelector(`label[for="proj-${checked[0]}"]`)?.textContent.trim() || 'Project'
            : `${checked.length} projects`;
          dropdownLabel.textContent = label;
          if (summary) summary.textContent = checked.length > 1
            ? Array.from(projectChecks).filter(c => c.checked)
                .map(c => document.querySelector(`label[for="proj-${c.id.replace('proj-','')}"]`)?.textContent.trim() || c.value)
                .join(', ')
            : '';
        }

        if (window.dashboardVueInstance?.loadData) {
          window.dashboardVueInstance.projectId = window.dashboardProjectId;
          window.dashboardVueInstance.loadData();
        }
      }

      if (allCheckbox) {
        allCheckbox.addEventListener('change', function () {
          if (this.checked) {
            projectChecks.forEach(c => { c.checked = false; });
          }
          updateSelection();
        });
      }

      projectChecks.forEach(cb => {
        cb.addEventListener('change', function () {
          if (this.checked && allCheckbox) {
            allCheckbox.checked = false;
          }
          updateSelection();
        });
      });
    });
  </script>
  <script src="{{ mix('js/dashboard.js') }}"></script>
@endsection