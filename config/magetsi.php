<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Magetsi Backend API
    |--------------------------------------------------------------------------
    */

    'url' => env('MAGETSI_API_URL', 'https://magetsi.test'),

    // Channel handler for WhatsApp bot transactions
    'channel' => env('MAGETSI_CHANNEL', 'WHATSAPP'),

    // Request timeout in seconds
    'timeout' => env('MAGETSI_API_TIMEOUT', 30),

    // ZESA handler identifier
    'handlers' => [
        'zesa' => 'ZESA',
    ],
];
