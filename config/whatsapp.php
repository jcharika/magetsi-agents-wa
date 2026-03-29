<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Configuration
    |--------------------------------------------------------------------------
    */

    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'magetsi_verify_token'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'api_url' => 'https://graph.facebook.com/v21.0',

    /*
    |--------------------------------------------------------------------------
    | Meta App Secret (for signature verification)
    |--------------------------------------------------------------------------
    */

    'app_secret' => env('META_APP_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Flows Encryption
    |--------------------------------------------------------------------------
    |
    | The private key is used to decrypt incoming flow data requests.
    | The corresponding public key must be uploaded to Meta.
    | See: docs/whatsapp-flows-setup.md
    |
    */

    'flow_private_key_path' => env('WHATSAPP_FLOW_PRIVATE_KEY_PATH', base_path('private.pem')),
    'flow_private_key' => env('WHATSAPP_FLOW_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Flow IDs
    |--------------------------------------------------------------------------
    |
    | These are the Flow IDs from Meta WhatsApp Business Manager.
    | Set after uploading and publishing flows.
    |
    */

    'flows' => [
        'buy_zesa' => env('WHATSAPP_BUY_ZESA_FLOW_ID'),
        'settings' => env('WHATSAPP_SETTINGS_FLOW_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Product Configuration
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'zesa' => [
            'label' => 'ZESA Tokens',
            'icon' => '⚡',
            'currency' => 'ZWG',
            'min_amount' => 100,
            'quick_amounts' => [100, 200, 300, 500],
        ],
    ],
];
