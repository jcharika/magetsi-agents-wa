<?php

return [
    'backend' => env('MAGETSI_BACKEND', 'legacy'),
    'default_backend' => env('MAGETSI_DEFAULT_BACKEND', 'legacy'),

    'mock_password' => env('MOCK_PASSWORD', 'magetsi'),
    'mock_allowed_wa_ids' => explode(',', env('MOCK_ALLOWED_WA_IDS', '')),
    'mock_enabled' => false,

    'url' => env('MAGETSI_API_URL', 'https://magetsi.test'),
    'channel' => env('MAGETSI_CHANNEL', 'AGENTS'),

    'legacy_url' => env('MAGETSI_LEGACY_URL', 'https://magetsi.co.zw'),
    'legacy_token' => env('MAGETSI_LEGACY_TOKEN', ''),
    'legacy_email' => env('MAGETSI_LEGACY_EMAIL', 'agent@magetsi.co.zw'),
    'legacy_poll_attempts' => env('MAGETSI_LEGACY_POLL_ATTEMPTS', 10),
    'legacy_poll_interval' => env('MAGETSI_LEGACY_POLL_INTERVAL', 1000),

    'timeout' => env('MAGETSI_API_TIMEOUT', 30),
    'handlers' => [
        'zesa' => 'ZESA',
    ],
];
