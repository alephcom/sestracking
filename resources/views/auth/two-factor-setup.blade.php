<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Set up authenticator | SES Tracking</title>
  <link rel="stylesheet" href="{{ mix('css/app.css') }}">
  <link rel="stylesheet" href="{{ mix('css/signin.css') }}">
</head>
<body>

<div class="signin-wrapper">
  <div class="signin-card">
    <div class="signin-brand">
      <div class="signin-logo">
        <i class="fas fa-mobile-screen-button"></i>
      </div>
      <h1 class="signin-title">Set up two-factor authentication</h1>
      <p class="signin-subtitle">Scan this QR code with your authenticator app, then enter the 6-digit code to confirm.</p>
    </div>

    @if($errors->any())
      <div class="alert alert-danger mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
      </div>
    @endif

    <div class="text-center mb-4 p-3 bg-light rounded">{!! $qrSvg !!}</div>

    <p class="small text-muted text-center mb-4">If you cannot scan the code, enter this key manually:<br>
      <code class="user-select-all">{{ $secret }}</code>
    </p>

    <form method="post" action="{{ route('two-factor.setup.confirm') }}">
      @csrf
      <div class="mb-3">
        <label for="code" class="form-label visually-hidden">Verification code</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-key text-muted"></i></span>
          <input type="text"
                 name="code"
                 id="code"
                 class="form-control"
                 placeholder="6-digit code"
                 value="{{ old('code') }}"
                 autocomplete="one-time-code"
                 required
                 autofocus>
        </div>
      </div>

      <button class="btn btn-primary w-100 btn-signin" type="submit">
        <i class="fas fa-check me-2"></i>Confirm and continue
      </button>
    </form>
  </div>
</div>

</body>
</html>
