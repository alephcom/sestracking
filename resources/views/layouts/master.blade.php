<!DOCTYPE html>
<html class="h-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>@yield('site-title', '.') | SesDashboard</title>

  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">
  <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
  <meta name="msapplication-TileColor" content="#da532c">
  <meta name="theme-color" content="#ffffff">
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">

</head>
<body class="h-100">
<nav class="navbar navbar-dark fixed-top bg-colored flex-md-nowrap p-0 shadow">
  <a class="navbar-brand mr-0" href="/">SesDashboard</a>
  <ul class="navbar-nav px-3">
    <li class="nav-item text-nowrap">
      <a class="nav-link" href="{{ route('logout') }}">Sign out</a>
    </li>
  </ul>
</nav>

<div class="container-fluid h-100">
  <div class="row h-100" style="padding-top: 56px;">
    @include('layouts/sidebar')

    <main role="main" class="col-md-9 px-4 h-100 d-flex flex-column">
      <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
       @yield('h1')
      </div>


      @if(session('alert'))
        <div class="alert alert-">
              {{ session('alert') }}
        </div>
      @endif

           @yield('page-content')
      <footer class="footer mt-auto py-3 text-muted">
        © {{ 'now'|date('Y') }} <a href="https://sesdashboard.com/" target="_blank" class="text-muted text-decoration-underline">SesDashboard App</a>
      </footer>
    </main>
  </div>
</div>

</body>

@yield('scripts')

</html>
