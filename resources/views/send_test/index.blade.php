@extends('layouts.master')


@section('site-title')
    Send test e-mail
@endsection

@section('h1')
    <h1 class="h2">
        Send test e-mail
    </h1>
@endsection

@section('page-content')
  <form method="post" action="{{ route('send_test.send') }}">
    @csrf
    <input type="hidden" name="submit" value="1">

  <div class="form-row row">
    <div class="col-4 mt-1">
      <label class="form-label">From</label>
      <input type="text" class="form-control" name="sendFrom" value="{{ old('sendFrom','info@aleph-com.net') }}" placeholder=""/>
    </div>
    <div class="col-4 mt-1">
      <label class="form-label">To</label>
      <input type="text" class="form-control" name="sendTo" value="{{ old('sendTo','admin@system.com') }}" placeholder=""/>
    </div>
  </div>

  <div class="form-row row">
    <div class="col-4 mt-1">
      <label class="form-label">Subject</label>
      <input type="text" class="form-control" name="subject" value="{{ old('subject','SesDashboard test message') }}" placeholder="SesDashboard test message"/>
    </div>
    <div class="col-4">
      <label class="form-label">X-SES-CONFIGURATION-SET</label>
      <input type="text" class="form-control" name="configurationSet" value="{{ old('configurationSet') }}" placeholder=""/>
    </div>
  </div>

  <div class="form-row row">
    <div class="col-8 mt-1">
      <label class="form-label">Message</label>
      <textarea class="form-control" name="message">{{ old('message','This is a test message') }}</textarea>
    </div>
  </div>

  <button class="btn btn-primary mt-1" type="submit"><i class="far fa-paper-plane"></i> Send</button>

 </form>
@endsection
