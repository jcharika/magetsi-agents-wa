<?php

return [
    'customer' => [
        'enabled' => env('FLOWS_CUSTOMER_ENABLED', true),
        'flows' => ['customer', 'consumer'],
        'flow_id' => env('WHATSAPP_CUSTOMER_FLOW_ID'),
    ],
];
