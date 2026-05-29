<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active Backend
    |--------------------------------------------------------------------------
    |
    | "new"    → New API (magetsi.co.zw) — 4-step: prepare → validate → confirm → process
    | "legacy" → Legacy API (legacy.magetsi.co.zw) — 2-step: check meter → init + poll
    |
    */

    'backend' => env('MAGETSI_BACKEND', 'legacy'),

    /*
    |--------------------------------------------------------------------------
    | New Backend API (magetsi.co.zw)
    |--------------------------------------------------------------------------
    */

    'url' => env('MAGETSI_API_URL', 'https://magetsi.co.zw'),

    // Channel handler for WhatsApp bot transactions
    'channel' => env('MAGETSI_CHANNEL', 'AGENTS'),

    /*
    |--------------------------------------------------------------------------
    | Legacy Backend API (legacy.magetsi.co.zw)
    |--------------------------------------------------------------------------
    |
    | Uses the /api/zesa/v1/* endpoints with _token authentication.
    |
    */

    'legacy_url' => env('MAGETSI_LEGACY_URL', 'https://legacy.magetsi.co.zw'),

    // API token for legacy endpoint authentication
    'legacy_token' => env('MAGETSI_LEGACY_TOKEN', ''),

    // Default email for legacy transactions (required for Stripe, optional for EcoCash)
    'legacy_email' => env('MAGETSI_LEGACY_EMAIL', 'agent@legacy.magetsi.co.zw'),

    // Number of times to poll transaction status after init
    'legacy_poll_attempts' => env('MAGETSI_LEGACY_POLL_ATTEMPTS', 10),

    // Milliseconds between each poll attempt
    'legacy_poll_interval' => env('MAGETSI_LEGACY_POLL_INTERVAL', 3000),

    /*
    |--------------------------------------------------------------------------
    | Shared
    |--------------------------------------------------------------------------
    */

    // Request timeout in seconds
    'timeout' => env('MAGETSI_API_TIMEOUT', 30),

    // ZESA handler identifier
    'handlers' => [
        'zesa' => 'ZESA',
    ],
];
