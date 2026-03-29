# Channels - https://magetsi.test/channel/list

Response 
```json
{
    "message": "",
    "success": true,
    "body": {
        "list": [
            {
                "id": 1,
                "name": "Consumer",
                "description": "Consumer Channel",
                "handler": "CONSUMER",
                "status": 1,
                "created_at": "2026-03-28 16:45:40",
                "updated_at": "2026-03-28 16:45:40"
            },
            {
                "id": 2,
                "name": "Corporate",
                "description": "Corporate Channel",
                "handler": "CORPORATE",
                "status": 1,
                "created_at": "2026-03-28 16:45:40",
                "updated_at": "2026-03-28 16:45:40"
            },
            {
                "id": 3,
                "name": "WhatsApp",
                "description": "WhatsApp Channel",
                "handler": "WHATSAPP",
                "status": 1,
                "created_at": "2026-03-28 16:45:40",
                "updated_at": "2026-03-28 16:45:40"
            },
            {
                "id": 4,
                "name": "Admin",
                "description": "Admin",
                "handler": "ADMIN",
                "status": 1,
                "created_at": "2026-03-28 16:45:40",
                "updated_at": "2026-03-28 16:45:40"
            }
        ]
    }
}
```

# Prepare - Request URL
https://magetsi.test/transactions/prepare
Request Method POST

Request 
```json
{"handler":"ZESA","channel":"ADMIN"}
```

Response
```json
{
    "success": true,
    "trace": "69c80d75827b6318008519",
    "transaction_type": {
        "id": 1,
        "group_name": "Electricity",
        "group_description": "Buy Zesa Tokens",
        "name": "Electricity",
        "handler": "ZESA",
        "description": "Buy Zesa Tokens",
        "group_weight": 1,
        "weight": 1,
        "icon": "",
        "icon_type": "",
        "select_currency": 0,
        "status": true,
        "simulation": true,
        "batch": true,
        "hold": false,
        "requires_amount": 1,
        "message": "Zesa service is currently unavailable. Please try again later.",
        "created_at": "2026-03-28 16:45:40",
        "updated_at": "2026-03-28 16:45:56",
        "payment_currency": [
            "USD",
            "ZWG",
            "LTY"
        ],
        "debit_types": [
            {
                "handler": "CASH",
                "name": "Cash",
                "description": "Cash Payment",
                "requires_account": false,
                "redirect": false,
                "requires_authorisation": true,
                "currency": [
                    "ZWG",
                    "USD"
                ]
            },
            {
                "handler": "BANK",
                "name": "Bank",
                "description": "Bank Payment",
                "requires_account": false,
                "redirect": false,
                "requires_authorisation": true,
                "currency": [
                    "ZWG",
                    "USD"
                ]
            },
            {
                "handler": "ECOCASH",
                "name": "EcoCash",
                "description": "EcoCash Payment",
                "requires_account": true,
                "redirect": false,
                "requires_authorisation": false,
                "currency": [
                    "ZWG",
                    "USD"
                ]
            },
            {
                "handler": "INTERNATIONAL",
                "name": "International Card",
                "description": "International Payment",
                "requires_account": false,
                "redirect": true,
                "requires_authorisation": false,
                "currency": [
                    "USD"
                ]
            },
            {
                "handler": "WALLET",
                "name": "Wallet",
                "description": "Wallet Payment",
                "requires_account": true,
                "redirect": false,
                "requires_authorisation": false,
                "currency": [
                    "ZWG",
                    "USD",
                    "LTY"
                ]
            },
            {
                "handler": "MANUAL",
                "name": "Manual",
                "description": "Manual Payment",
                "requires_account": false,
                "redirect": false,
                "requires_authorisation": true,
                "currency": [
                    "ZWG",
                    "USD",
                    "LTY"
                ]
            }
        ]
    }
}
```

# Currencies api - https://magetsi.test/currency/list

Response 
```json
{
  "message": "",
  "success": true,
  "body": {
    "list": [
      {
        "id": 1,
        "name": "Zimbabwean Dollar",
        "code": "ZWG",
        "iso_code": "924",
        "base": 1,
        "description": "Zim Gold Coin",
        "created_at": "2026-03-28 16:45:40",
        "updated_at": "2026-03-28 16:45:40",
        "select_name": "Zimbabwean Dollar - ZWG"
      },
      {
        "id": 2,
        "name": "United States Dollar",
        "code": "USD",
        "iso_code": "840",
        "base": 0,
        "description": "United States Dollar",
        "created_at": "2026-03-28 16:45:40",
        "updated_at": "2026-03-28 16:45:40",
        "select_name": "United States Dollar - USD"
      },
      {
        "id": 3,
        "name": "Loyalty Points",
        "code": "LTY",
        "iso_code": "600",
        "base": 0,
        "description": "Loyalty Points",
        "created_at": "2026-03-28 16:45:40",
        "updated_at": "2026-03-28 16:45:40",
        "select_name": "Loyalty Points - LTY"
      }
    ]
  }
}
```

# Transaction validation - POST https://magetsi.test/transactions/validate


Request
```json
{"handler":"ZESA","channel":"ADMIN","owner":"USER","origination":"TRANSACTION","user_id":"Test","guest_id":"","trace":"69c80d75827b6318008519","uid":"","biller_account":"840","amount":"","product_code":"","currency":"","recipient_name":"","recipient_last_name":"","recipient_address":"","recipient_currency":"","recipient_phone":"","recipient_email":"","narration":"","payment":[],"event_id":"","ticket_type_id":"","quantity":1,"biller_id":"","gift_voucher_id":"","voucher_type_id":"","message":""}
```

Response
```json
{
    "success": true,
    "recipient_name": "USD Test Meter",
    "recipient_address": "United States of America",
    "biller_account": "840",
    "recipient_currency": "USD",
    "currency": "USD",
    "debit": [
        {
            "handler": "CASH",
            "name": "Cash",
            "description": "Cash Payment",
            "requires_account": false,
            "redirect": false,
            "requires_authorisation": true,
            "currency": [
                "USD"
            ]
        },
        {
            "handler": "BANK",
            "name": "Bank",
            "description": "Bank Payment",
            "requires_account": false,
            "redirect": false,
            "requires_authorisation": true,
            "currency": [
                "USD"
            ]
        },
        {
            "handler": "ECOCASH",
            "name": "EcoCash",
            "description": "EcoCash Payment",
            "requires_account": true,
            "redirect": false,
            "requires_authorisation": false,
            "currency": [
                "USD"
            ]
        },
        {
            "handler": "INTERNATIONAL",
            "name": "International Card",
            "description": "International Payment",
            "requires_account": false,
            "redirect": true,
            "requires_authorisation": false,
            "currency": [
                "USD"
            ]
        },
        {
            "handler": "WALLET",
            "name": "Wallet",
            "description": "Wallet Payment",
            "requires_account": true,
            "redirect": false,
            "requires_authorisation": false,
            "currency": [
                "USD",
                "LTY"
            ]
        },
        {
            "handler": "MANUAL",
            "name": "Manual",
            "description": "Manual Payment",
            "requires_account": false,
            "redirect": false,
            "requires_authorisation": true,
            "currency": [
                "USD",
                "LTY"
            ]
        }
    ],
    "trace": "69c80d75827b6318008519",
    "bundles": []
}
```

# Confirm transaction - POST https://magetsi.test/transactions/confirm

Request
```json
{"handler":"ZESA","channel":"ADMIN","owner":"USER","origination":"TRANSACTION","user_id":"Test","guest_id":"","trace":"69c80d75827b6318008519","uid":"","biller_account":"840","amount":10,"product_code":"","currency":"USD","recipient_name":"USD Test Meter","recipient_last_name":"","recipient_address":"United States of America","recipient_currency":"USD","recipient_phone":"","recipient_email":"","narration":"","payment":[{"configuration":{"handler":"ECOCASH","name":"EcoCash","description":"EcoCash Payment","requires_account":true,"redirect":false,"requires_authorisation":false,"currency":["USD"]},"handler":"ECOCASH","account":"0771846212","amount":10,"currency":"USD","verified":{},"isVerified":false}],"event_id":"","ticket_type_id":"","quantity":1,"biller_id":"","gift_voucher_id":"","voucher_type_id":"","message":""}
```

Response
```json
{
    "success": true,
    "confirmation": {
        "transaction_type": {
            "name": "Transaction Type",
            "value": "Electricity",
            "type": "value",
            "group": "info"
        },
        "biller_account": {
            "name": "Biller Account",
            "value": "840",
            "type": "value",
            "group": "info"
        },
        "recipient_name": {
            "name": "Name",
            "value": "USD Test Meter",
            "type": "value",
            "group": "info"
        },
        "recipient_address": {
            "name": "Address",
            "value": "United States of America",
            "type": "value",
            "group": "info"
        },
        "amount_1": {
            "name": "Transaction Amount",
            "value": "(USD) 10",
            "type": "value",
            "group": "amount"
        },
        "amount_2": {
            "name": "Discount",
            "value": "(USD) 1",
            "type": "value",
            "group": "amount"
        },
        "amount_3": {
            "name": "Bonus",
            "value": "(USD) 1",
            "type": "value",
            "group": "amount"
        },
        "amount_4": {
            "name": "Service Fees",
            "value": "(USD) 0.88",
            "type": "value",
            "group": "amount"
        },
        "amount_5": {
            "name": "Loyalty Points",
            "value": "(USD) 1",
            "type": "value",
            "group": "amount"
        },
        "payment-0": {
            "name": "EcoCash Payment (07****12)",
            "value": "(USD) 9.88",
            "type": "value",
            "group": "payment"
        }
    },
    "payment": [
        {
            "handler": "ECOCASH",
            "weight": 9,
            "description": "EcoCash Payment",
            "amount": 9.88,
            "currency": "USD",
            "transaction_currency": "USD",
            "account": "0771846212",
            "requires_authorisation": false
        }
    ],
    "amounts": {
        "1": {
            "amount": "10.00000000",
            "currency": "USD",
            "impact": "add",
            "taget_impact": "transaction",
            "type": "principal",
            "name": "Transaction Amount"
        },
        "2": {
            "amount": "1.00000000",
            "currency": "USD",
            "impact": "subtract",
            "taget_impact": "payment",
            "type": "discount",
            "name": "Discount",
            "uid": "ZESA"
        },
        "3": {
            "amount": "1.00000000",
            "currency": "USD",
            "impact": "add",
            "taget_impact": "transaction",
            "type": "bonus",
            "name": "Bonus",
            "uid": "ZESA"
        },
        "4": {
            "amount": "0.88",
            "currency": "USD",
            "impact": "add",
            "taget_impact": "payment",
            "type": "service-fee",
            "name": "Service Fees",
            "uid": "ZESA"
        },
        "5": {
            "amount": "1.00000000",
            "currency": "USD",
            "impact": "add",
            "taget_impact": "wallet",
            "type": "loyalty",
            "name": "Loyalty Points",
            "uid": "ZESA"
        }
    }
}
```

# Process Transaction - 
https://magetsi.test/transactions/process

Request
```json
{"handler":"ZESA","channel":"ADMIN","owner":"GUEST","origination":"TRANSACTION","user_id":"","guest_id":"Agent 10","trace":"69c8110feb293708611874","uid":"","biller_account":"840","amount":10,"product_code":"","currency":"USD","recipient_name":"USD Test Meter","recipient_last_name":"","recipient_address":"United States of America","recipient_currency":"USD","recipient_phone":"","recipient_email":"","narration":"","payment":[{"configuration":{"handler":"ECOCASH","name":"EcoCash","description":"EcoCash Payment","requires_account":true,"redirect":false,"requires_authorisation":false,"currency":["USD"]},"handler":"ECOCASH","account":"0771846212","amount":10,"currency":"USD","verified":{},"isVerified":false}],"event_id":"","ticket_type_id":"","quantity":1,"biller_id":"","gift_voucher_id":"","voucher_type_id":"","message":""}
```

Response
```json
{
    "success": true,
    "transaction": {
        "handler": "ZESA",
        "product_code": null,
        "channel": "ADMIN",
        "currency": "USD",
        "action": "PROCESS",
        "amount": "10.00000000",
        "local_amount": "263.65600000",
        "payment_amount": 9.88,
        "local_payment_amount": "260.49212800",
        "bill_amount": 11,
        "local_bill_amount": "290.02160000",
        "description": "Buy Zesa Tokens",
        "narration": null,
        "biller_status": "PENDING",
        "payment_status": "PENDING",
        "status": "PENDING",
        "biller_account": "840",
        "recipient_name": "USD Test Meter",
        "recipient_last_name": null,
        "recipient_address": "United States of America",
        "recipient_currency": "USD",
        "recipient_phone": null,
        "recipient_email": null,
        "uid": "69c8114479125121603943",
        "external_uid": "69c8114479125121603943",
        "trace": "69c8110feb293708611874",
        "owner": "GUEST",
        "guest_id": "Agent 10",
        "user_id": 1,
        "division_id": null,
        "corporate_id": null,
        "origination": "TRANSACTION",
        "cart_id": null,
        "batch_id": null,
        "updated_at": "2026-03-28 17:35:00",
        "created_at": "2026-03-28 17:35:00",
        "id": 2,
        "customer_reference": "813923"
    },
    "payments": [
        {
            "owner": "GUEST",
            "origination": "TRANSACTION",
            "transaction_id": 2,
            "weight": 9,
            "user_id": 1,
            "division_id": null,
            "corporate_id": null,
            "handler": "ECOCASH",
            "description": "EcoCash Payment",
            "narration": null,
            "currency": "USD",
            "transaction_currency": "USD",
            "amount": 9.88,
            "local_amount": "260.49212800",
            "account": "0771846212",
            "requires_authorisation": false,
            "status": "PROCESSING",
            "reference": "69c8114479125121603943",
            "updated_at": "2026-03-28 17:35:00",
            "created_at": "2026-03-28 17:35:00",
            "id": 2,
            "dispatch": true
        }
    ],
    "redirect": false,
    "url": null
}
```