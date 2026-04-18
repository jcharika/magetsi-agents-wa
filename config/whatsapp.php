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

    'api_url' => 'https://graph.facebook.com/v25.0',

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
    | Flow Delivery Mode
    |--------------------------------------------------------------------------
    |
    | How flows are sent to the user:
    |
    | "interactive" — sends an interactive flow message with a CTA button.
    | Works inside user-initiated conversations (24h window).
    | No template approval needed.
    |
    | "template" — sends a pre-approved message template with a FLOW
    | button. Works for business-initiated conversations
    | (outside 24h window). Requires template approval.
    |
    */

    'flow_mode' => env('WHATSAPP_FLOW_MODE', 'interactive'),

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
    | Flow Template Names
    |--------------------------------------------------------------------------
    |
    | When flow_mode = "template", these are the approved template names
    | that have a FLOW button attached. Each template must be created in
    | Meta Business Suite with a FLOW button pointing to the correct flow.
    |
    | See: docs/whatsapp-flows-setup.md
    |
    */

    'flow_templates' => [
        'buy_zesa' => env('WHATSAPP_BUY_ZESA_TEMPLATE', 'buy_zesa_flow'),
        'settings' => env('WHATSAPP_SETTINGS_TEMPLATE', 'settings_flow'),
    ],

    // Language code for templates (must match template creation language)
    'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),

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
