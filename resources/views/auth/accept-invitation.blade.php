<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <title>Accept Invitation | SesDashboard</title>

  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="manifest" href="/site.webmanifest">
  <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
  <meta name="msapplication-TileColor" content="#da532c">
  <meta name="theme-color" content="#ffffff">
  @vite(['resources/css/app.scss', 'resources/css/signin.scss'])

</head>
<body class="text-center">
<form class="form-signin" method="post" action="{{ route('invitation.accept') }}">
  SesDashboard
  <h1 class="h3 mb-3 font-weight-normal">Accept Invitation</h1>
  <p class="text-muted mb-3">Set your password to complete your account setup</p>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <label for="email" class="sr-only">Email address</label>
  <input type="email" value="{{ old('email', $email) }}" name="email" id="email" class="form-control" placeholder="Email address" required readonly>
  
  <label for="password" class="sr-only mt-2">Password</label>
  <input type="password" name="password" id="password" class="form-control mt-2" placeholder="Password (min 8 characters)" required autofocus minlength="8">
  
  <label for="password_confirmation" class="sr-only mt-2">Confirm Password</label>
  <input type="password" name="password_confirmation" id="password_confirmation" class="form-control mt-2" placeholder="Confirm Password" required minlength="8">
  
  <input type="hidden" name="token" value="{{ $token }}">
  @csrf
  
  <button class="btn btn-lg btn-primary btn-block mt-3" type="submit">Set Password & Sign In</button>
</form>
</body>
</html>

