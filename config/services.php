<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

        // Reject webhook notifications that are not signed by Amazon SNS.
        // Subscription confirmations are always verified regardless of this flag.
        'webhook_verify_signatures' => env('SES_WEBHOOK_VERIFY_SIGNATURES', true),

        // Accept raw SES event JSON (not wrapped in an SNS envelope). These cannot be
        // signature-checked, so they are only accepted when this is enabled.
        'webhook_allow_direct_payloads' => env('SES_WEBHOOK_ALLOW_DIRECT_PAYLOADS', false),

        // Optional comma-separated SNS TopicArn patterns (shell wildcards allowed), e.g.
        // "arn:aws:sns:*:123456789012:*". When set, messages from other topics are rejected,
        // so a leaked webhook URL cannot be subscribed to someone else's topic.
        'webhook_allowed_topic_arns' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SES_WEBHOOK_ALLOWED_TOPIC_ARNS', ''))
        ))),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'microsoft' => [
        'client_id'     => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect'      => env('MICROSOFT_REDIRECT_URI'),
        'tenant'        => env('MICROSOFT_TENANT_ID', 'common'),
    ],

];
