<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // Disk SFTP buat membaca dataset CSV mentah dari VPS sumber.
        'vps_dataset' => [
            'driver' => 'sftp',
            'host' => env('VPS_HOST'),
            'port' => (int) env('VPS_PORT', 22),
            'username' => env('VPS_USERNAME'),

            'privateKey' => env('VPS_PRIVATE_KEY')
                ? base64_decode(env('VPS_PRIVATE_KEY'))
                : null,

            'root' => env('VPS_SOURCE_DIR', '/var/www/syslog-datasets'),

            'visibility' => 'private',
            'directory_visibility' => 'private',

            'timeout' => (int) env('VPS_TIMEOUT', 30),
            'maxTries' => (int) env('VPS_MAX_TRIES', 3),
            'throw' => true,
        ],

        // Disk SFTP buat menyimpan upload dan hasil deteksi ke VPS target.
        'vps_csv' => [
            'driver' => 'sftp',
            'host' => env('VPS_HOST'),
            'port' => (int) env('VPS_PORT', 22),
            'username' => env('VPS_USERNAME'),

            'privateKey' => env('VPS_PRIVATE_KEY')
                ? base64_decode(env('VPS_PRIVATE_KEY'))
                : null,

            'root' => env('VPS_TARGET_DIR', env('VPS_REMOTE_DIR', '/var/www/revanasanreigo')),

            'visibility' => 'private',
            'directory_visibility' => 'private',

            'timeout' => (int) env('VPS_TIMEOUT', 30),
            'maxTries' => (int) env('VPS_MAX_TRIES', 3),
            'throw' => true,
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
