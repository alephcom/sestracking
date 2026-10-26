<form method="post" action="{{ route('edit_profile.update') }}">
  <div class="form-row mt-1">
    <div class="col-4">
      <label class="form-label">Name</label>
      <input type="text" name="name" value="{{ $form->name??'' }}" class="form-control" />
    </div>
  </div>

  <div class="form-row mt-1">
    <div class="col-4">
      <label class="form-label">Email</label>
      <input type="text" name="email" value="{{ $form->email??'' }}" class="form-control" />
    </div>
  </div>

  <div class="form-row mt-1">
    <div class="col-4">
      <label class="form-label">Username</label>
      <input type="text" name="username" value="{{ $form->username??'' }}" class="form-control" />
    </div>
  </div>

  <div class="form-row mt-1">
    <div class="col-4">
      <label class="form-label">Password</label>
      <input type="password" name="password" value="" class="form-control" />
    </div>
  </div>

  <div class="form-row mt-1">
    <div class="col-4">
      <label class="form-label">Confirm Password</label>
      <input type="password" name="password_confirmation" value="" class="form-control" />
    </div>
  </div>

  @if($form->password !== null)
  <div class="form-row mt-1">
    <div class="col-4">
      <label class="form-label">Current Password</label>
      <input type="password" name="current_password" value="" class="form-control" autocomplete="current-password" />
      <small class="form-text text-muted">Required to change your email or password.</small>
    </div>
  </div>
  @endif

  @if($errors->any())
  <div class="alert alert-danger mt-2">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  @csrf
  <button class="btn btn-primary mt-1" type="submit">Save</button>

</form>