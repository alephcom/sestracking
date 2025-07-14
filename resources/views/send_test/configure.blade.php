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
  <div class="alert alert-light">
    <div class="alert alert-danger"><code>SES MAILER</code> is not configured.</div>
    <p>To be able to send e-mails you need to set "Transport" configuration.
      There is already installed Amazon SES Transport.
      To provide SES credentials, open <code>.env</code> file and find following:</p>
    <div class="alert alert-secondary"><code>AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION</code></div>
    <p>Set value to <code>AWS_ACCESS_KEY_ID</code>, <code>AWS_SECRET_ACCESS_KEY</code> and <code>AWS_DEFAULT_REGION</code> keys with your SES settings.</p>
    <p><a href="">Refresh page</a>, when it's done.</p>
    <p>More information about Laravel Mailer:
      <a href="https://laravel.com/docs/11.x/mail" target="_blank">https://laravel.com/docs/11.x/mail</a>
    </p>
    <p>More about SES SMTP interface:
      <a href="https://docs.aws.amazon.com/ses/latest/DeveloperGuide/send-email-smtp.html" target="_blank">
        https://docs.aws.amazon.com/ses/latest/DeveloperGuide/send-email-smtp.html
      </a>
    </p>
  </div>
@endsection
