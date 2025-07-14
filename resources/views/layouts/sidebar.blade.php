<nav class="d-none d-md-block col-md-3 sidebar">
  <div>
    <ul class="nav flex-column">
      <li class="nav-item">
        <a class="nav-link" href="{{ route('dashboard.index') }}">
          <i class="fas fa-tachometer-alt"></i>
          Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="{{ route('activity') }}">
          <i class="fas fa-list"></i>
          Activity
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#">
          <i class="fas fa-folder-open"></i>
          Reports <sup>TODO</sup>
        </a>
      </li>
    </ul>

    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
      <span>Settings</span>
    </h6>
    <ul class="nav flex-column mb-2">
      <li class="nav-item">
        <a class="nav-link" href="{{ route('send_test') }}">
          <i class="far fa-paper-plane"></i>
          Send Test Mail
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="{{ route('edit_profile') }}">
          <i class="fas fa-user-cog"></i>
          Account
        </a>
      </li>
    </ul>

    @if(auth()->user()->isAdmin())
    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
      <span>Admin</span>
    </h6>
    <ul class="nav flex-column mb-2">
      <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.projects.index') }}">
          <i class="fas fa-project-diagram"></i>
          Manage Projects
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="{{ route('admin.users.index') }}">
          <i class="fas fa-users"></i>
          Manage Users
        </a>
      </li>
    </ul>
    @endif
  </div>
</nav>
