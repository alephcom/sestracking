<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Two-factor authentication | SES Tracking</title>
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">
  <link rel="stylesheet" href="{{ mix('css/signin.css') }}">
</head>
<body>

<div class="signin-wrapper">
  <div class="signin-card">
    <div class="signin-brand">
      <div class="signin-logo">
        <i class="fas fa-shield-halved"></i>
      </div>
      <h1 class="signin-title">Verify your identity</h1>
      <p class="signin-subtitle">Enter the code from your authenticator app for<br><strong>{{ $emailHint }}</strong></p>
    </div>

    @if($errors->any())
      <div class="alert alert-danger mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
      </div>
    @endif

    <form method="post" action="{{ route('two-factor.challenge.confirm') }}">
      @csrf
      <div class="mb-3">
        <label for="code" class="form-label visually-hidden">Authentication code</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-key text-muted"></i></span>
          <input type="text"
                 name="code"
                 id="code"
                 class="form-control"
                 placeholder="6-digit code or recovery code"
                 value="{{ old('code') }}"
                 autocomplete="one-time-code"
                 required
                 autofocus>
        </div>
      </div>

      <button class="btn btn-primary w-100 btn-signin mb-3" type="submit">
        <i class="fas fa-check me-2"></i>Continue
      </button>
    </form>

    <form method="post" action="{{ route('two-factor.challenge.cancel') }}" class="text-center">
      @csrf
      <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0">
        Cancel and sign in again
      </button>
    </form>
  </div>
</div>

</body>
</html>
