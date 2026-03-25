<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Set new password | SES Tracking</title>

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

    <div class="signin-brand">
      <div class="signin-logo">
        <i class="fas fa-lock"></i>
      </div>
      <h1 class="signin-title">Set new password</h1>
      <p class="signin-subtitle">Choose a strong password for your account.</p>
    </div>

    @if($errors->any())
      <div class="alert alert-danger mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
      </div>
    @endif

    <form method="post" action="{{ route('password.update') }}">
      @csrf

      <input type="hidden" name="token" value="{{ $token }}">

      <div class="mb-3">
        <label for="inputEmail" class="form-label visually-hidden">Email address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
          <input type="email"
                 name="email"
                 id="inputEmail"
                 class="form-control"
                 placeholder="Email address"
                 value="{{ old('email', $email) }}"
                 required
                 autocomplete="username">
        </div>
      </div>

      <div class="mb-3">
        <label for="inputPassword" class="form-label visually-hidden">New password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
          <input type="password"
                 name="password"
                 id="inputPassword"
                 class="form-control"
                 placeholder="New password (min 8 characters)"
                 required
                 minlength="8"
                 autocomplete="new-password"
                 autofocus>
        </div>
      </div>

      <div class="mb-4">
        <label for="inputPasswordConfirm" class="form-label visually-hidden">Confirm password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
          <input type="password"
                 name="password_confirmation"
                 id="inputPasswordConfirm"
                 class="form-control"
                 placeholder="Confirm password"
                 required
                 minlength="8"
                 autocomplete="new-password">
        </div>
      </div>

      <button class="btn btn-primary w-100 btn-signin mb-3" type="submit">
        <i class="fas fa-check me-2"></i>Reset password
      </button>
    </form>

    <p class="text-center mb-0">
      <a href="{{ route('login') }}" class="text-decoration-none">Back to sign in</a>
    </p>
  </div>
</div>

</body>
</html>
