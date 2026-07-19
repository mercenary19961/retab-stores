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

    'tamara' => [
        'api_token' => env('TAMARA_API_TOKEN'),
        'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
        'base_url' => env('TAMARA_BASE_URL', 'https://api.tamara.co'),
        'country' => env('TAMARA_COUNTRY', 'SA'),
        'instalments' => env('TAMARA_INSTALMENTS', 3),
        'currency' => env('TAMARA_CURRENCY', 'SAR'),
    ],

    // Cloudflare Turnstile (bot gate on public forms). Both keys unset in dev →
    // the widget renders nothing and TurnstileVerifier no-ops.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // WhatsApp Cloud API (direct Meta, no BSP). driver=log in dev (no network);
    // driver=cloud sends live. admin_recipients = comma-separated E.164 numbers
    // that receive the new-order alerts.
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com/v21.0'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),          // X-Hub-Signature-256 verification
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),      // webhook GET handshake
        'default_language' => env('WHATSAPP_DEFAULT_LANGUAGE', 'ar'),
        'admin_recipients' => env('WHATSAPP_ADMIN_RECIPIENTS'),
        // Approximate cost per MARKETING template message, for the campaign
        // send-cost estimate. Meta prices marketing messages per country; set
        // this to Meta's current Saudi Arabia marketing rate. Default ≈ $0.037.
        'marketing_rate' => (float) env('WHATSAPP_MARKETING_RATE', 0.14),
        'rate_currency' => env('WHATSAPP_RATE_CURRENCY', 'SAR'),
    ],

];
