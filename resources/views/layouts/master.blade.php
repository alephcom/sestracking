<!DOCTYPE html>
<html class="h-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>@yield('site-title', '.') | SES Tracking</title>

  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">
  <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#667eea">
  <meta name="msapplication-TileColor" content="#3b82f6">
  <meta name="theme-color" content="#3b82f6">
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">

</head>
<body class="h-100">
<nav class="navbar navbar-dark fixed-top bg-colored flex-md-nowrap p-0 shadow-sm">
  <div class="d-flex align-items-center">
    {{-- Desktop sidebar toggle --}}
    <button class="btn btn-link text-white ms-2 d-none d-md-block" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
      <i class="fas fa-bars"></i>
    </button>
    {{-- Mobile offcanvas toggle --}}
    <button class="btn btn-link text-white ms-2 d-md-none" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Open menu">
      <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand" href="/">
      <i class="fas fa-chart-line me-2"></i>
      SES Tracking
    </a>
  </div>
  <ul class="navbar-nav px-3">
    <li class="nav-item text-nowrap">
      <a class="nav-link" href="{{ route('logout') }}">
        <i class="fas fa-sign-out-alt me-1"></i> Sign out
      </a>
    </li>
  </ul>
</nav>

{{-- Mobile offcanvas sidebar --}}
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel" style="width: 280px; padding-top: 56px;">
  <div class="offcanvas-header border-bottom py-3">
    <h6 class="offcanvas-title fw-bold text-dark" id="mobileNavLabel">
      <i class="fas fa-chart-line me-2 text-primary"></i>SES Tracking
    </h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
    <ul class="nav flex-column pt-2">
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.index') ? 'active' : '' }}" href="{{ route('dashboard.index') }}">
          <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('activity') ? 'active' : '' }}" href="{{ route('activity') }}">
          <i class="fas fa-list"></i><span>Activity</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
          <i class="fas fa-folder-open"></i><span>Reports</span>
        </a>
      </li>
    </ul>

    <h6 class="sidebar-heading"><span>Settings</span></h6>
    <ul class="nav flex-column mb-2">
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('send_test') ? 'active' : '' }}" href="{{ route('send_test') }}">
          <i class="far fa-paper-plane"></i><span>Send Test Mail</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('edit_profile') ? 'active' : '' }}" href="{{ route('edit_profile') }}">
          <i class="fas fa-user-cog"></i><span>Account</span>
        </a>
      </li>
      @if(!auth()->user()->isSuperAdmin())
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('project-requests.create') ? 'active' : '' }}" href="{{ route('project-requests.create') }}">
          <i class="fas fa-hand-paper"></i><span>Request Project</span>
        </a>
      </li>
      @endif
    </ul>

    @if(auth()->user()->isSuperAdmin() || auth()->user()->isAdminForAnyProject())
    <h6 class="sidebar-heading"><span>Admin</span></h6>
    <ul class="nav flex-column mb-2">
      @if(auth()->user()->isSuperAdmin())
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('project-requests.*') ? 'active' : '' }}" href="{{ route('project-requests.index') }}">
          <i class="fas fa-clipboard-list"></i><span>Project Requests</span>
        </a>
      </li>
      @endif
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.projects.*') ? 'active' : '' }}" href="{{ route('admin.projects.index') }}">
          <i class="fas fa-project-diagram"></i><span>Manage Projects</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
          <i class="fas fa-users"></i><span>Manage Users</span>
        </a>
      </li>
    </ul>
    @endif
  </div>
</div>

<div class="container-fluid h-100">
  <div class="row h-100" style="padding-top: 56px;">
    @include('layouts/sidebar')

    <main role="main" class="col-12 col-md-9 px-3 px-md-4 h-100 d-flex flex-column">
      <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-3 mb-4 border-bottom">
       @yield('h1')
      </div>

      @if(session('alert'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
          {{ session('alert') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

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

      @yield('page-content')
      
      <footer class="footer mt-auto py-4 text-muted">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            © {{ now()->year }} <a href="https://sestracking.com/" target="_blank">SES Tracking</a>
          </div>
          <div class="text-muted small">
            <i class="fas fa-heart text-danger"></i> Built with modern design
          </div>
        </div>
      </footer>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.querySelector('.sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  
  if (!sidebar) {
    console.error('Sidebar element not found');
    return;
  }
  
  if (!sidebarToggle) {
    console.error('Sidebar toggle button not found');
    return;
  }
  
  // Check localStorage for saved state
  const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
  
  if (isCollapsed) {
    sidebar.classList.add('collapsed');
    const icon = sidebarToggle.querySelector('i');
    if (icon) {
      icon.classList.remove('fa-bars');
      icon.classList.add('fa-chevron-right');
    }
  }
  
  // Toggle sidebar
  sidebarToggle.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const wasCollapsed = sidebar.classList.contains('collapsed');
    sidebar.classList.toggle('collapsed');
    const isNowCollapsed = sidebar.classList.contains('collapsed');
    
    console.log('Sidebar toggled:', { wasCollapsed, isNowCollapsed, classes: sidebar.className });
    
    localStorage.setItem('sidebarCollapsed', isNowCollapsed);
    
    // Update icon
    const icon = this.querySelector('i');
    if (icon) {
      if (isNowCollapsed) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-chevron-right');
      } else {
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-bars');
      }
    }
  });
});
</script>

@yield('scripts')

</body>
</html>
