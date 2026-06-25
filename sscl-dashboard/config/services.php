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

    // Config koneksi ke Flask ML API untuk proses prediksi file trafik.
    'ml_api' => [
        'url' => env('ML_API_URL', 'http://127.0.0.1:5001'),
        'key' => env('ML_API_KEY', 'dev-secret-key'),
        'timeout' => env('ML_API_TIMEOUT', 120),
    ],

    // Config notifikasi setelah proses scan selesai.
    'scan_notifications' => [
        'admin_email' => env('SCAN_NOTIFICATION_ADMIN_EMAIL'),
        'send_to_user' => env('SCAN_NOTIFICATION_SEND_TO_USER', true),
    ],

    // Config integrasi API UPA-TIK untuk ambil dataset trafik otomatis.
    'upa_tik' => [
        'enabled' => env('UPA_TIK_API_ENABLED', false),
        'base_url' => env('UPA_TIK_API_BASE_URL'),
        'endpoint' => env('UPA_TIK_API_ENDPOINT'),
        'token' => env('UPA_TIK_API_TOKEN'),
        'timeout' => (int) env('UPA_TIK_API_TIMEOUT', 30),
        'system_user_email' => env('UPA_TIK_API_SYSTEM_USER_EMAIL'),
        'source_name' => 'UPA-TIK',
    ],

    // Config sinkronisasi CSV dari dan ke VPS.
    'vps_csv' => [
        'enabled' => env('VPS_CSV_ENABLED', false),
        'source_disk' => env('VPS_CSV_SOURCE_DISK', 'vps_dataset'),
        'target_disk' => env('VPS_CSV_TARGET_DISK', 'vps_csv'),
        'source_directory' => env('VPS_CSV_SOURCE_DIRECTORY', ''),
        'target_uploads_directory' => env('VPS_CSV_TARGET_UPLOADS_DIRECTORY', 'scan-uploads'),
        'target_results_directory' => env('VPS_CSV_TARGET_RESULTS_DIRECTORY', 'scan-results'),
        'system_user_email' => env('VPS_CSV_SYSTEM_USER_EMAIL', env('UPA_TIK_API_SYSTEM_USER_EMAIL')),
        'source_name' => env('VPS_CSV_SOURCE_NAME', 'VPS Syslog Dataset'),
        'max_files' => (int) env('VPS_CSV_MAX_FILES', 10),
        'delete_after_processing' => env('VPS_CSV_DELETE_AFTER_PROCESSING', false),
        'schedule' => env('VPS_CSV_SCHEDULE', 'daily'),
        'schedule_time' => env('VPS_CSV_SCHEDULE_TIME', '00:00'),
        'schedule_timezone' => env('VPS_CSV_SCHEDULE_TIMEZONE', 'Asia/Jakarta'),
    ],

];
