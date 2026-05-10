@php($actions = config('ses_suppression.iam_actions', []))
<div class="alert alert-info small mb-0">
    <p class="mb-2 fw-semibold">IAM permissions for this app (SES account-level suppression list)</p>
    <p class="mb-2">Attach these actions to the IAM user or role for the <strong>per-project</strong> Access Key ID and Secret Access Key below (global <code>.env</code> AWS keys are not used for suppression). Use <code>"Resource": "*"</code> unless your organization restricts further. The region you set for this project must match the AWS region where you send mail with SES.</p>
    <ul class="mb-0 ps-3">
        @foreach($actions as $action => $description)
            <li><code>{{ $action }}</code> — {{ $description }}</li>
        @endforeach
    </ul>
</div>
