<?php

return [
    'customer' => [
        'enabled' => env('FLOWS_CUSTOMER_ENABLED', false),
        'flows' => ['customer'],
        'flow_id' => env('WHATSAPP_CUSTOMER_FLOW_ID'),
    ],
];