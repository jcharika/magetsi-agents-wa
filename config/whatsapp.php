<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Configuration — Agent Bot
    |--------------------------------------------------------------------------
    */

    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'magetsi_verify_token'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API Configuration — Customer Bot
    |--------------------------------------------------------------------------
    |
    | Configure a separate WhatsApp Business Account for the customer-facing
    | chatbot. Uses its own webhook endpoint (/api/customer/webhook).
    |
    */

    'customer_token' => env('WHATSAPP_CUSTOMER_TOKEN'),
    'customer_phone_number_id' => env('WHATSAPP_CUSTOMER_PHONE_NUMBER_ID'),
    'customer_verify_token' => env('WHATSAPP_CUSTOMER_VERIFY_TOKEN', 'magetsi_customer_verify'),
    'customer_business_account_id' => env('WHATSAPP_CUSTOMER_BUSINESS_ACCOUNT_ID'),

    'api_url' => 'https://graph.facebook.com/v25.0',

    /*
    |--------------------------------------------------------------------------
    | Meta App Secret
    |--------------------------------------------------------------------------
    |
    | agent_secret — used by the /api/webhook and /api/flow-data endpoints
    | customer_secret — used by the /api/customer/webhook and /api/customer/flow-data endpoints
    |
    */

    'app_secret' => env('META_APP_SECRET'),
    'customer_app_secret' => env('WHATSAPP_CUSTOMER_APP_SECRET'),

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
    | Flow Delivery Mode — Agent
    |--------------------------------------------------------------------------
    */

    'flow_mode' => env('WHATSAPP_FLOW_MODE', 'interactive'),

    /*
    |--------------------------------------------------------------------------
    | Flow Delivery Mode — Customer
    |--------------------------------------------------------------------------
    */

    'customer_flow_mode' => env('WHATSAPP_CUSTOMER_FLOW_MODE', 'interactive'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Flow IDs — Agent
    |--------------------------------------------------------------------------
    */

    'flows' => [
        'buy_zesa' => env('WHATSAPP_BUY_ZESA_FLOW_ID'),
        'settings' => env('WHATSAPP_SETTINGS_FLOW_ID'),
        'customer' => env('WHATSAPP_CUSTOMER_FLOW_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Flow IDs — Customer
    |--------------------------------------------------------------------------
    */

    'customer_flows' => [
        'customer' => env('WHATSAPP_CUSTOMER_FLOW_ID'),
        'zesa' => env('WHATSAPP_CUSTOMER_ZESA_FLOW_ID'),
        'airtime' => env('WHATSAPP_CUSTOMER_AIRTIME_FLOW_ID'),
        'bundle' => env('WHATSAPP_CUSTOMER_BUNDLE_FLOW_ID'),
        'telone' => env('WHATSAPP_CUSTOMER_TELONE_FLOW_ID'),
        'biller' => env('WHATSAPP_CUSTOMER_BILLER_FLOW_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flow Template Names
    |--------------------------------------------------------------------------
    */

    'flow_templates' => [
        'buy_zesa' => env('WHATSAPP_BUY_ZESA_TEMPLATE', 'buy_zesa_flow'),
        'settings' => env('WHATSAPP_SETTINGS_TEMPLATE', 'settings_flow'),
        'customer' => env('WHATSAPP_CUSTOMER_TEMPLATE', 'customer_flow'),
    ],

    'customer_flow_templates' => [
        'customer' => env('WHATSAPP_CUSTOMER_TEMPLATE', 'customer_flow'),
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
