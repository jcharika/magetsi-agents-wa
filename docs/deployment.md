# Deployment Guide

## Prerequisites

- PHP 8.2+
- Composer
- A publicly accessible HTTPS server (e.g., Laravel Forge, DigitalOcean, or ngrok for development)
- A Meta Business Account with WhatsApp API access

## Quick Start (Local Development)

### 1. Clone & Install

```bash
git clone <repo-url>
cd magetsi-agents
composer install
cp .env.example .env   # or just edit .env
php artisan key:generate
```

### 2. Database

The project uses SQLite by default:

```bash
php artisan migrate --seed
```

### 3. Generate WhatsApp Flows Keypair

```bash
php artisan whatsapp:generate-keypair
```

### 4. Configure `.env`

```env
# WhatsApp Cloud API
WHATSAPP_TOKEN=your_permanent_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_VERIFY_TOKEN=your_custom_verify_token
WHATSAPP_BUSINESS_ACCOUNT_ID=your_waba_id

# Flows Encryption
META_APP_SECRET=your_meta_app_secret
WHATSAPP_FLOW_PRIVATE_KEY_PATH=storage/app/whatsapp/private.pem

# Flow IDs (set after creating flows in Meta)
WHATSAPP_BUY_ZESA_FLOW_ID=
WHATSAPP_SETTINGS_FLOW_ID=

# Magetsi Backend
MAGETSI_API_URL=https://magetsi.test
MAGETSI_CHANNEL=AGENTS
```

### 5. Run the Simulator

```bash
php artisan serve --port=8111
```

Visit `http://localhost:8111/` to test locally.

### 6. Expose for WhatsApp (Development)

Use ngrok to expose your local server:

```bash
ngrok http 8111
```

Use the ngrok HTTPS URL as your webhook and flow endpoint URL.

## Production Deployment

### Server Requirements

- PHP 8.2+ with extensions: `openssl`, `json`, `curl`, `mbstring`
- HTTPS with valid SSL certificate
- Composer

### Environment

Set all `.env` variables for production. Key differences:

```env
APP_ENV=production
APP_DEBUG=false

# Real Magetsi API
MAGETSI_API_URL=https://magetsi.co.zw
```

### Routes Overview

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/` | Simulator UI (development only) |
| `POST` | `/simulate` | Simulator API (development only) |
| `GET` | `/simulate/flow/{id}` | Simulator flow schema (development only) |
| `GET` | `/api/webhook` | WhatsApp webhook verification |
| `POST` | `/api/webhook` | WhatsApp incoming messages |
| `POST` | `/api/flow-data` | WhatsApp Flows encrypted data exchange |

### Disabling Simulator in Production

The simulator routes are in `routes/web.php`. To disable in production, wrap them:

```php
if (app()->environment('local', 'staging')) {
    Route::get('/', [SimulatorController::class, 'index']);
    Route::post('/simulate', [SimulatorController::class, 'simulate']);
    Route::get('/simulate/flow/{flowId}', [SimulatorController::class, 'flowSchema']);
}
```

## Magetsi Backend API

The chatbot communicates with the Magetsi backend for real transactions.

### Transaction Flow

```
prepare → validate → confirm → process
```

| Endpoint | Method | Purpose |
|---|---|---|
| `/transactions/prepare` | POST | Get trace ID + payment methods |
| `/transactions/validate` | POST | Validate meter → customer info |
| `/transactions/confirm` | POST | Get fee breakdown |
| `/transactions/process` | POST | Execute transaction |

### Configuration

```env
MAGETSI_API_URL=https://magetsi.test
MAGETSI_CHANNEL=AGENTS
MAGETSI_API_TIMEOUT=30
```

## File Structure

```
app/
├── Console/Commands/
│   └── GenerateFlowKeypair.php     # php artisan whatsapp:generate-keypair
├── Http/Controllers/
│   ├── FlowDataController.php      # Encrypted flow data exchange
│   ├── SimulatorController.php     # Local simulator
│   └── WhatsAppWebhookController.php
├── Models/
│   ├── Agent.php
│   ├── AgentProduct.php
│   └── Transaction.php
└── Services/
    ├── ConversationHandler.php     # Chat state machine
    ├── FlowEncryptionService.php   # RSA + AES-GCM encryption
    ├── MagetsiApiService.php       # Backend API client
    ├── MeterValidationService.php  # Meter validation via API
    └── WhatsAppService.php         # Cloud API wrapper

config/
├── magetsi.php                     # Backend API config
└── whatsapp.php                    # WhatsApp + encryption config

docs/
├── deployment.md                   # This file
└── whatsapp-flows-setup.md         # Flows encryption setup

resources/flows/
├── buy_zesa.json                   # Buy ZESA flow definition
└── settings.json                   # Settings flow definition
```
