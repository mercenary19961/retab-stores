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
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'oto' => [
        'refresh_token' => env('OTO_REFRESH_TOKEN'),
        'base_url' => env('OTO_BASE_URL', 'https://api.tryoto.com/rest/v2'),
        'origin_city' => env('OTO_ORIGIN_CITY', 'Riyadh'),
        'webhook_secret' => env('OTO_WEBHOOK_SECRET'),
    ],

    'moyasar' => [
        'secret_key' => env('MOYASAR_SECRET_KEY'),
        'base_url' => env('MOYASAR_BASE_URL', 'https://api.moyasar.com/v1'),
        'currency' => env('MOYASAR_CURRENCY', 'SAR'),
        'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
    ],

];
