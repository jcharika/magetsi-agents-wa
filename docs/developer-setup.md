# Developer Setup Guide

## Prerequisites

- PHP 8.3+
- Composer
- Node.js 18+ with npm
- SQLite (default) or MySQL
- A [Meta Business Account](https://business.facebook.com) with WhatsApp API access
- [ngrok](https://ngrok.com) (for local development webhook)

---

## Quick Start

### 1. Clone & Install Dependencies

```bash
git clone <repo-url>
cd magetsi-agents
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your WhatsApp credentials:

```env
# WhatsApp Cloud API
WHATSAPP_TOKEN=your_permanent_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
WHATSAPP_VERIFY_TOKEN=magetsi_verify_token
WHATSAPP_BUSINESS_ACCOUNT_ID=your_waba_id

# Flows Encryption
META_APP_SECRET=your_meta_app_secret
WHATSAPP_FLOW_PRIVATE_KEY_PATH=storage/app/whatsapp/private.pem

# Flow IDs (set after creating flows in Meta)
WHATSAPP_BUY_ZESA_FLOW_ID=
WHATSAPP_SETTINGS_FLOW_ID=

# Magetsi Backend
MAGETSI_BACKEND=legacy
MAGETSI_API_URL=https://magetsi.co.zw
MAGETSI_CHANNEL=AGENTS
```

See `.env.example` for all available variables.

### 3. Database

```bash
php artisan migrate --seed
```

SQLite is used by default. The database file is created at `database/database.sqlite`.

### 4. Generate WhatsApp Flows Keypair

```bash
php artisan whatsapp:generate-keypair
```

This creates `storage/app/whatsapp/private.pem` and `public.pem`. The public key must be uploaded to Meta (see [WhatsApp Flows Setup](whatsapp-flows-setup.md)).

### 5. Build Frontend Assets

```bash
npm run build
```

For development with hot-reload:

```bash
npm run dev
```

---

## Running the Application

### All-in-one dev command

```bash
composer run dev
```

This runs four processes concurrently:
| Process | Command | Color |
|---|---|---|
| Server | `php artisan serve` | Blue |
| Queue | `php artisan queue:listen --tries=1 --timeout=0` | Purple |
| Logs | `php artisan pail --timeout=0` | Pink |
| Vite | `npm run dev` | Orange |

### Manual start

```bash
php artisan serve --port=8111
```

Visit `http://localhost:8111/` for the simulator UI.

---

## Exposing for WhatsApp (ngrok)

WhatsApp requires a public HTTPS endpoint for webhooks and flows.

```bash
ngrok http 8111
```

Use the ngrok URL (e.g., `https://abc123.ngrok.io`) as your webhook URL and flow endpoint.

---

## WhatsApp Cloud API Setup

### Webhook Configuration

In Meta App Dashboard → WhatsApp → Configuration:

| Setting | Value |
|---|---|
| Webhook URL | `https://your-domain.com/api/webhook` |
| Verify Token | The value of `WHATSAPP_VERIFY_TOKEN` in `.env` |
| Webhook Fields | Subscribe to `messages` |

### Flow Endpoint Configuration

For each flow, set the endpoint URL:

```bash
curl -X POST \
  "https://graph.facebook.com/v25.0/<FLOW_ID>" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"endpoint_uri": "https://your-domain.com/api/flow-data"}'
```

**The endpoint must be HTTPS with a valid SSL certificate.**

### Automated Setup

```bash
php artisan whatsapp:configure --flows --templates --publish --endpoint=https://your-domain.com
```

Use `--dry-run` to preview without making API calls.

---

## Project Structure

```
├── app/
│   ├── Bot/
│   │   ├── BotFlow.php              # Abstract base class for bot flows
│   │   └── OnboardingFlow.php       # New agent onboarding flow
│   ├── Console/Commands/
│   │   ├── GenerateFlowKeypair.php  # php artisan whatsapp:generate-keypair
│   │   └── WhatsAppConfigure.php    # php artisan whatsapp:configure
│   ├── Contracts/
│   │   └── TransactionBackend.php   # Backend interface
│   ├── Http/Controllers/
│   │   ├── FlowDataController.php       # Encrypted flow data exchange
│   │   ├── SimulatorController.php      # Local browser simulator
│   │   ├── WhatsAppVerifyWebhookController.php  # Webhook verification
│   │   └── WhatsAppWebhookController.php        # Incoming messages
│   ├── Models/
│   │   ├── Agent.php
│   │   ├── AgentProduct.php
│   │   └── Transaction.php
│   └── Services/
│       ├── WhatsAppService.php               # Cloud API wrapper
│       ├── FlowEncryptionService.php         # RSA + AES-GCM encryption
│       ├── MeterValidationService.php        # Meter validation
│       ├── BackendManager.php                # Backend factory
│       ├── MagetsiApiService.php             # New API client
│       ├── LegacyMagetsiService.php          # Legacy API client
│       └── Conversation/
│           ├── ConversationHandler.php       # Chat routing
│           ├── ConversationSession.php       # Cache-backed session
│           ├── FlowEngine.php                # Bot flow engine
│           ├── ZesaConversationHandler.php   # ZESA flow handler
│           ├── SettingsConversationHandler.php  # Settings flow handler
│           └── SimulatorMessageCollector.php # Test message capture
├── config/
│   ├── magetsi.php                  # Backend API config
│   └── whatsapp.php                 # WhatsApp + encryption config
├── resources/
│   ├── flows/
│   │   ├── buy_zesa.json            # Buy ZESA flow definition
│   │   └── settings.json            # Settings flow definition
│   └── views/
│       ├── simulator.blade.php      # Simulator UI
│       └── welcome.blade.php        # Laravel welcome page
├── routes/
│   ├── api.php                      # Webhook + flow-data routes
│   └── web.php                      # Simulator routes
├── docs/
│   ├── features.md                  # Feature documentation
│   ├── deployment.md                # Deployment guide
│   └── whatsapp-flows-setup.md      # Flows encryption setup
│   └── developer-setup.md           # This file
└── tests/
    ├── Feature/
    │   └── ExampleTest.php
    └── Unit/
        └── ExampleTest.php
```

---

## Running Tests

```bash
composer run test
```

This runs `php artisan config:clear` then `php artisan test`.

---

## Adding a New Bot Flow

1. Create a class in `app/Bot/` that extends `BotFlow`
2. Implement `name()`, `steps()`, and `onComplete()`
3. Optionally override `shouldActivate()` and `priority()`
4. The `FlowEngine` auto-discovers it via filesystem scan

Example:

```php
<?php

namespace App\Bot;

use App\Models\Agent;

class SupportFlow extends BotFlow
{
    public function name(): string
    {
        return 'support';
    }

    public function steps(): array
    {
        return [
            'issue' => [
                'message' => 'Please describe your issue:',
                'validate' => fn($input) => strlen($input) >= 10,
                'error' => 'Please provide at least 10 characters.',
                'save_as' => 'issue',
                'next' => null, // terminal
            ],
        ];
    }

    public function onComplete(Agent $agent, array $data, object $handler): void
    {
        $handler->sendTextMessage($agent->wa_id, 'Your issue has been logged.');
    }
}
```

---

## Switching Backends

The project supports two backends for transaction processing:

| `.env` Setting | `MAGETSI_BACKEND=legacy` | `MAGETSI_BACKEND=new` |
|---|---|---|
| API Base | `legacy.magetsi.co.zw` (live) | `magetsi.co.zw` (live) |
| Lifecycle | check meter → init + poll | prepare → validate → confirm → process |
| Auth | Token + email | Channel + handler |

Both backends implement the `TransactionBackend` contract in `app/Contracts/`.

---

## Common Tasks

### Generate new RSA key pair
```bash
php artisan whatsapp:generate-keypair --force
```

### Clear cache
```bash
php artisan optimize:clear
```

### View logs (with Pail)
```bash
php artisan pail
```

### Queue worker (for database queue)
```bash
php artisan queue:listen --tries=1 --timeout=0
```

### Run Laravel Pint (code style)
```bash
./vendor/bin/pint
```

---

## Troubleshooting

### "Decryption failed" in flow data exchange
- Verify private key matches uploaded public key
- Check `WHATSAPP_FLOW_PRIVATE_KEY_PATH` or `WHATSAPP_FLOW_PRIVATE_KEY` is set
- Generate a new keypair with `--force` and re-upload

### "Invalid signature" errors
- Verify `META_APP_SECRET` matches your Meta App Dashboard secret
- The app connected to the flow must match the app secret

### Simulator not working
- Ensure database is migrated (`php artisan migrate`)
- Check the simulator is not wrapped in `app()->environment('local')` check

### Flow not appearing in WhatsApp
- Ensure flow is published (`php artisan whatsapp:configure --publish`)
- For template mode: template must be approved by Meta
- For interactive mode: only works in user-initiated conversations within 24-hour window
