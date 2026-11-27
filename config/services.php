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

    // The image-generation API. No defaults: the base URL and the header it
    // signs webhooks with come from the environment.
    'image_provider' => [
        'base_url' => env('IMAGE_PROVIDER_BASE_URL'),
        // One API key per environment: each key has its own webhook URL, signing
        // secret and usage report, so this app's records can match it exactly.
        'api_key' => env('IMAGE_PROVIDER_API_KEY'),
        'webhook_secret' => env('IMAGE_PROVIDER_WEBHOOK_SECRET'),
        'signature_header' => env('IMAGE_PROVIDER_SIGNATURE_HEADER'),
    ],

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

];
