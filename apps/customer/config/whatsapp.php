<?php

return [
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'magetsi_verify_token'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'api_url' => 'https://graph.facebook.com/v25.0',

    'app_secret' => env('META_APP_SECRET'),

    'flow_private_key_path' => env('WHATSAPP_FLOW_PRIVATE_KEY_PATH', base_path('private.pem')),
    'flow_private_key' => env('WHATSAPP_FLOW_PRIVATE_KEY'),

    'flow_mode' => env('WHATSAPP_FLOW_MODE', 'interactive'),

    'flows' => [
        'customer' => env('WHATSAPP_CUSTOMER_FLOW_ID'),
    ],

    'flow_templates' => [
        'customer' => env('WHATSAPP_CUSTOMER_TEMPLATE', 'customer_flow'),
    ],

    'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),

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
