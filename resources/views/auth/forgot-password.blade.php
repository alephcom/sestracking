<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Reset password | SES Tracking</title>

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
        <i class="fas fa-key"></i>
      </div>
      <h1 class="signin-title">Forgot password</h1>
      <p class="signin-subtitle">Enter your email and we will send reset instructions if an account exists.</p>
    </div>

    @if(session('status'))
      <div class="alert alert-success mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('status') }}
      </div>
    @endif

    @if($errors->any())
      <div class="alert alert-danger mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
      </div>
    @endif

    <form method="post" action="{{ route('password.email') }}">
      @csrf

      <div class="mb-4">
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

      <button class="btn btn-primary w-100 btn-signin mb-3" type="submit">
        <i class="fas fa-paper-plane me-2"></i>Send reset link
      </button>
    </form>

    <p class="text-center mb-0">
      <a href="{{ route('login') }}" class="text-decoration-none">Back to sign in</a>
    </p>
  </div>
</div>

</body>
</html>
