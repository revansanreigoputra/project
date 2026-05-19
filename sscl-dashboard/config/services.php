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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ml_api' => [
        'url' => env('ML_API_URL', 'http://127.0.0.1:5001'),
        'key' => env('ML_API_KEY', 'dev-secret-key'),
        'timeout' => env('ML_API_TIMEOUT', 120),
    ],

    'scan_notifications' => [
        'admin_email' => env('SCAN_NOTIFICATION_ADMIN_EMAIL'),
        'send_to_user' => env('SCAN_NOTIFICATION_SEND_TO_USER', true),
    ],

];
