<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Recovery codes | SES Tracking</title>
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">
  <link rel="stylesheet" href="{{ mix('css/signin.css') }}">
</head>
<body>

<div class="signin-wrapper">
  <div class="signin-card">
    <div class="signin-brand">
      <div class="signin-logo">
        <i class="fas fa-life-ring"></i>
      </div>
      <h1 class="signin-title">Save your recovery codes</h1>
      <p class="signin-subtitle">Store these in a safe place. Each code works once if you lose your authenticator device.</p>
    </div>

    <ul class="list-group list-group-flush mb-4 border rounded">
      @foreach($recoveryCodes as $code)
        <li class="list-group-item font-monospace user-select-all">{{ $code }}</li>
      @endforeach
    </ul>

    <a href="{{ route('dashboard.index') }}" class="btn btn-primary w-100 btn-signin">
      <i class="fas fa-arrow-right me-2"></i>Continue to dashboard
    </a>
  </div>
</div>

</body>
</html>
