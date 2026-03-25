<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Sign in | SES Tracking</title>

  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">
  <meta name="theme-color" content="#3b82f6">
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">
  <link rel="stylesheet" href="{{ mix('css/signin.css') }}">
</head>
<body>

<div class="signin-wrapper">
  <div class="signin-card">

    {{-- Branding --}}
    <div class="signin-brand">
      <div class="signin-logo">
        <i class="fas fa-chart-line"></i>
      </div>
      <h1 class="signin-title">SES Tracking</h1>
      <p class="signin-subtitle">Monitor your Amazon SES email delivery</p>
    </div>

    {{-- Error alert --}}
    @if($error ?? false)
      <div class="alert alert-danger mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ $error }}
      </div>
    @endif

    @if(session('success'))
      <div class="alert alert-success mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
      </div>
    @endif

    @if($errors->any())
      <div class="alert alert-danger mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
      </div>
    @endif

    {{-- SSO buttons (only rendered when credentials are configured) --}}
    @php
      $googleEnabled    = filled(config('services.google.client_id'));
      $microsoftEnabled = filled(config('services.microsoft.client_id'));
      $anySso           = $googleEnabled || $microsoftEnabled;
    @endphp

    @if($anySso)
    <div class="sso-buttons">
      @if($googleEnabled)
      <a href="{{ route('social.redirect', 'google') }}" class="btn-sso btn-sso-google">
        <svg class="sso-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Continue with Google
      </a>
      @endif
      @if($microsoftEnabled)
      <a href="{{ route('social.redirect', 'microsoft') }}" class="btn-sso btn-sso-microsoft">
        <svg class="sso-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M11.4 24H0V12.6h11.4V24z" fill="#F1511B"/>
          <path d="M24 24H12.6V12.6H24V24z" fill="#80CC28"/>
          <path d="M11.4 11.4H0V0h11.4v11.4z" fill="#00ADEF"/>
          <path d="M24 11.4H12.6V0H24v11.4z" fill="#FBBC09"/>
        </svg>
        Continue with Microsoft
      </a>
      @endif
    </div>

    {{-- Divider --}}
    <div class="signin-divider">
      <span>or sign in with email</span>
    </div>
    @endif

    {{-- Email/password form --}}
    <form method="post" action="{{ route('login') }}">
      @csrf
      <input type="hidden" name="submit" value="1">

      <div class="mb-3">
        <label for="inputEmail" class="form-label visually-hidden">Email address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
          <input type="email"
                 name="email"
                 id="inputEmail"
                 class="form-control"
                 placeholder="Email address"
                 value="{{ old('email') }}"
                 required
                 autofocus>
        </div>
      </div>

      <div class="mb-2">
        <label for="inputPassword" class="form-label visually-hidden">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
          <input type="password"
                 name="password"
                 id="inputPassword"
                 class="form-control"
                 placeholder="Password"
                 required>
        </div>
      </div>

      <div class="mb-4 text-end">
        <a href="{{ route('password.request') }}" class="small text-decoration-none">Forgot password?</a>
      </div>

      <button class="btn btn-primary w-100 btn-signin" type="submit">
        <i class="fas fa-sign-in-alt me-2"></i>Sign in
      </button>
    </form>

  </div>
</div>

</body>
</html>
