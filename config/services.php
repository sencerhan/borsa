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

    // BitKuruş (BK$) zinciri — yatırma doğrulaması için düğüm API'si.
    'bitkurush' => [
        'base_url' => env('BITKURUS_API_URL', 'https://bitkurush.org'),
        'activity_limit' => (int) env('BITKURUS_ACTIVITY_LIMIT', 50),
    ],

    // Tron / TronGrid — USDT (TRC20) yatırma doğrulaması için.
    'trongrid' => [
        'base_url' => env('TRONGRID_API_URL', 'https://api.trongrid.io'),
        'api_key' => env('TRONGRID_API_KEY'),
        // Ana ağ USDT (TRC20) sözleşmesi.
        'usdt_contract' => env('TRON_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
        'limit' => (int) env('TRONGRID_LIMIT', 50),
    ],

];
