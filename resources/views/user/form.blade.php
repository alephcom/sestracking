<form method="post">
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

  @csrf
  <input type="hidden" name="submit" value="1">
  <button class="btn btn-primary mt-1" type="submit">Save</button>

</form>