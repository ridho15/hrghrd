<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'hrgroup' => [
        'enabled' => env('HRG_ENABLED', true),
        'show_sso_button' => env('HRG_SHOW_SSO_BUTTON', false),
        'issuer' => env('HRG_ISSUER', 'http://localhost:8080/realms/hrgroup'),
        'client_id' => env('HRG_CLIENT_ID', 'hrg-hrd'),
        'client_secret' => env('HRG_CLIENT_SECRET', 'hrd_secret_local_2026'),
        'redirect_uri' => env('HRG_REDIRECT_URI', 'http://localhost:8000/auth/hr-group/callback'),
    ],

];
