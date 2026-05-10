<nav class="d-none d-md-block col-md-3 sidebar" id="sidebar">
  <div class="sidebar-content">
    <ul class="nav flex-column">
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.index') ? 'active' : '' }}" href="{{ route('dashboard.index') }}" data-tooltip="Dashboard">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('activity') ? 'active' : '' }}" href="{{ route('activity') }}" data-tooltip="Activity">
          <i class="fas fa-list"></i>
          <span>Activity</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}" data-tooltip="Reports">
          <i class="fas fa-folder-open"></i>
          <span>Reports</span>
        </a>
      </li>
    </ul>

    <h6 class="sidebar-heading">
      <span>Settings</span>
    </h6>
    <ul class="nav flex-column mb-2">
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('ses-suppression.*') ? 'active' : '' }}" href="{{ route('ses-suppression.chooser') }}" data-tooltip="SES suppression">
          <i class="fas fa-ban"></i>
          <span>SES suppression</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('send_test') ? 'active' : '' }}" href="{{ route('send_test') }}" data-tooltip="Send Test Mail">
          <i class="far fa-paper-plane"></i>
          <span>Send Test Mail</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('edit_profile') ? 'active' : '' }}" href="{{ route('edit_profile') }}" data-tooltip="Account">
          <i class="fas fa-user-cog"></i>
          <span>Account</span>
        </a>
      </li>
      @if(!auth()->user()->isSuperAdmin())
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('project-requests.create') ? 'active' : '' }}" href="{{ route('project-requests.create') }}" data-tooltip="Request Project">
          <i class="fas fa-hand-paper"></i>
          <span>Request Project</span>
        </a>
      </li>
      @endif
    </ul>

    @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdminForAnyProject())
    <h6 class="sidebar-heading">
      <span>Admin</span>
    </h6>
    <ul class="nav flex-column mb-2">
      @if(auth()->user()->isSuperAdmin())
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('project-requests.*') ? 'active' : '' }}" href="{{ route('project-requests.index') }}" data-tooltip="Project Requests">
          <i class="fas fa-clipboard-list"></i>
          <span>Project Requests</span>
        </a>
      </li>
      @endif
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.projects.*') ? 'active' : '' }}" href="{{ route('admin.projects.index') }}" data-tooltip="Manage Projects">
          <i class="fas fa-project-diagram"></i>
          <span>Manage Projects</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}" data-tooltip="Manage Users">
          <i class="fas fa-users"></i>
          <span>Manage Users</span>
        </a>
      </li>
    </ul>
    @endif
  </div>
</nav>
