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

Then edit `.env` with your credentials (see each section below).

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

Visit `http://localhost:8111/` for the app.

---

## Exposing for WhatsApp (ngrok)

WhatsApp requires public HTTPS endpoints for webhooks and flows.

```bash
ngrok http 8111
```

Use the ngrok URL (e.g., `https://abc123.ngrok.io`) as your webhook URL and flow endpoint.

```env
APP_URL=https://abc123.ngrok.io
```

---

## Agent Bot Setup

The agent bot is the primary WhatsApp chatbot used by agents to process transactions (ZESA, bill payments, etc.).

### Required `.env` Variables

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

# Flow delivery
WHATSAPP_FLOW_MODE=interactive

# Magetsi Backend
MAGETSI_BACKEND=legacy
MAGETSI_API_URL=https://magetsi.co.zw
MAGETSI_CHANNEL=AGENTS
```

### Webhook Configuration

In Meta App Dashboard → WhatsApp → Configuration:

| Setting | Value |
|---|---|
| Webhook URL | `https://your-domain.com/api/webhook` |
| Verify Token | `WHATSAPP_VERIFY_TOKEN` in `.env` |
| Webhook Fields | Subscribe to `messages` |

### Flow Endpoint Configuration

Set the same endpoint URL for both flows:

```bash
# Buy ZESA
curl -X POST "https://graph.facebook.com/v25.0/<BUY_ZESA_FLOW_ID>" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"endpoint_uri": "https://your-domain.com/api/flow-data"}'

# Settings  
curl -X POST "https://graph.facebook.com/v25.0/<SETTINGS_FLOW_ID>" \
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

## Customer Bot Setup

The customer bot is a separate WhatsApp chatbot for end customers to buy ZESA, airtime, bundles, pay TelOne, bills, and contact support — all through a single WhatsApp Flow (no separate product flows).

### Overview

| Aspect | Agent Bot | Customer Bot |
|---|---|---|
| Users | Registered agents | End customers |
| Flows | Separate per product | One unified flow (`customer.json`) |
| Webhook endpoint | `/api/webhook` | `/api/customer/webhook` |
| Flow-data endpoint | `/api/flow-data` | `/api/customer/flow-data` |
| Encryption keys | Shared key pair | Same key pair |
| WABA | Agent's WABA | Separate customer WABA |
| Transaction processing | Job-based (queued) | Job-based (same jobs) |

### Required `.env` Variables

```env
# WhatsApp Cloud API — Customer Bot
WHATSAPP_CUSTOMER_TOKEN=your_customer_bot_token
WHATSAPP_CUSTOMER_PHONE_NUMBER_ID=your_customer_phone_number_id
WHATSAPP_CUSTOMER_VERIFY_TOKEN=magetsi_customer_verify
WHATSAPP_CUSTOMER_BUSINESS_ACCOUNT_ID=your_customer_waba_id

# Customer Flow
WHATSAPP_CUSTOMER_FLOW_ID=the_single_customer_flow_id
WHATSAPP_CUSTOMER_FLOW_MODE=interactive

# App Secret (used for webhook signature verification)
WHATSAPP_CUSTOMER_APP_SECRET=your_customer_meta_app_secret
```

### Webhook Configuration

In Meta App Dashboard → WhatsApp → Configuration (for the **customer** WABA):

| Setting | Value |
|---|---|
| Webhook URL | `https://your-domain.com/api/customer/webhook` |
| Verify Token | `WHATSAPP_CUSTOMER_VERIFY_TOKEN` in `.env` |
| Webhook Fields | Subscribe to `messages` |

### Customer Flow Setup

The customer bot uses a **single multi-screen flow** defined in `resources/flows/customer.json` containing all screens:

| Screen | Service | Description |
|---|---|---|
| `HOME_SCREEN` | — | NavigationList with all enabled services |
| `ZESA_SCREEN` | ZESA | Buy prepaid electricity tokens |
| `AIRTIME_SCREEN` | Airtime | Buy airtime for any network |
| `BUNDLES_SCREEN` | Bundles | Buy data bundles |
| `TELONE_HOME_SCREEN` | TelOne | TelOne WiFi (ZWG/USD choice) |
| `TELONE_SCREEN` | TelOne | TelOne WiFi (ZWG) |
| `TELONE_USD_SCREEN` | TelOne | TelOne WiFi (USD) |
| `BILLERS_SCREEN` | Billers | Pay billers |
| `SUPPORT_SCREEN` | Support | Contact support |

#### Create the Flow in Meta

1. Go to **WhatsApp Manager** → **Account tools** → **Flows**
2. Click **Create Flow** → Name: "Magetsi Customer" → Category: "Other"
3. In the editor, switch to JSON mode and paste the contents of `resources/flows/customer.json`
4. Note the returned Flow ID and set `WHATSAPP_CUSTOMER_FLOW_ID` in `.env`

#### Set the Flow Endpoint URL

```bash
curl -X POST "https://graph.facebook.com/v25.0/<CUSTOMER_FLOW_ID>" \
  -H "Authorization: Bearer <CUSTOMER_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"endpoint_uri": "https://your-domain.com/api/customer/flow-data"}'
```

#### Publishing

```bash
curl -X POST "https://graph.facebook.com/v25.0/<CUSTOMER_FLOW_ID>/publish" \
  -H "Authorization: Bearer <CUSTOMER_TOKEN>"
```

### Service Toggles

Each service can be enabled/disabled independently from the admin panel at **Configuration → Customer Configuration → Services**. Disabled services are:

- Hidden from the `NavigationList` in the simulator
- Rejected with a redirect to `HOME_SCREEN` in production via `CustomerFlowHandler::isCustomerServiceEnabled()`

| Env Variable | Default | Service |
|---|---|---|
| `WHATSAPP_CUSTOMER_SERVICE_ZESA` | `true` | ZESA Tokens |
| `WHATSAPP_CUSTOMER_SERVICE_AIRTIME` | `true` | Airtime |
| `WHATSAPP_CUSTOMER_SERVICE_BUNDLES` | `true` | Data Bundles |
| `WHATSAPP_CUSTOMER_SERVICE_TELONE` | `true` | TelOne WiFi |
| `WHATSAPP_CUSTOMER_SERVICE_BILLERS` | `true` | Billers |
| `WHATSAPP_CUSTOMER_SERVICE_SUPPORT` | `true` | Support |

---

## Simulator

The built-in simulator at `/admin/simulate` (requires authentication) lets you test both bots without interacting with the real WhatsApp API.

### Agent Mode

Opens a WhatsApp-like shell connected to the agent conversation handler. Type "hi" to start, then use keywords like `zesa`, `settings`, `help`, `customer`.

### Customer Mode

Opens the same shell but routes to `SimulatorController::simulateCustomer()`. The customer flow (`customer.json`) is fetched and rendered with multi-screen navigation, form rendering, and mock data exchange.

Toggle between modes in the admin simulator view.

---

## Project Structure

```
├── app/
│   ├── Bot/
│   │   ├── BotFlow.php                  # Abstract base class
│   │   └── OnboardingFlow.php           # New agent onboarding flow
│   ├── Console/Commands/
│   │   ├── GenerateFlowKeypair.php
│   │   └── WhatsAppConfigure.php
│   ├── Contracts/
│   │   └── TransactionBackend.php
│   ├── Http/Controllers/
│   │   ├── AdminController.php          # Admin panel CRUD
│   │   ├── Auth/LoginController.php     # Admin login
│   │   ├── CustomerFlowController.php   # Customer flow data endpoint
│   │   ├── CustomerWebhookController.php # Customer incoming messages
│   │   ├── CustomerVerifyWebhookController.php # Customer verify
│   │   ├── FlowDataController.php       # Agent flow data endpoint
│   │   ├── SimulatorController.php      # Local browser simulator
│   │   ├── WhatsAppVerifyWebhookController.php
│   │   └── WhatsAppWebhookController.php
│   │   └── Traits/FlowDataController/
│   │       ├── CustomerFlowHandler.php  # Customer flow business logic
│   │       └── FlowDataControllerShared.php
│   ├── Models/
│   │   ├── Agent.php
│   │   ├── Customer.php
│   │   ├── AgentProduct.php
│   │   ├── Transaction.php
│   │   └── User.php
│   └── Services/
│       ├── WhatsAppService.php
│       ├── FlowEncryptionService.php
│       ├── MeterValidationService.php
│       ├── BackendManager.php
│       ├── MagetsiApiService.php
│       ├── LegacyMagetsiService.php
│       └── Conversation/
│           ├── ConversationHandler.php
│           ├── ConversationSession.php
│           ├── FlowEngine.php
│           ├── ZesaConversationHandler.php
│           ├── SettingsConversationHandler.php
│           └── SimulatorMessageCollector.php
├── config/
│   ├── flows.php                       # Feature flags
│   ├── magetsi.php                     # Backend API config
│   └── whatsapp.php                    # WhatsApp + flow config
├── resources/
│   ├── flows/
│   │   ├── buy_zesa.json               # Agent: ZESA flow
│   │   ├── settings.json               # Agent: Settings flow
│   │   └── customer.json               # Customer: unified multi-screen flow
│   └── views/
│       ├── admin/                       # Admin panel views
│       ├── simulator.blade.php          # Simulator UI
│       └── welcome.blade.php
├── routes/
│   ├── api.php                         # Webhook + flow-data routes
│   └── web.php                         # Admin + simulator routes
├── docs/
│   ├── features.md
│   ├── deployment.md
│   ├── whatsapp-flows-setup.md
│   └── developer-setup.md              # This file
└── migrations/
    ├── add_blocked_to_agents_table.php
    ├── add_blocked_to_users_table.php
    ├── create_customers_table.php
    └── add_customer_id_to_transactions_table.php
```

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

### Queue worker
```bash
php artisan queue:listen --tries=1 --timeout=0
```

### Create admin user
```bash
php artisan admin:user-create
```

### Run Pint (code style)
```bash
./vendor/bin/pint
```

---

## Troubleshooting

### "Decryption failed" in flow data exchange
- Verify private key matches uploaded public key
- Check `WHATSAPP_FLOW_PRIVATE_KEY_PATH` is set
- Generate a new keypair with `--force` and re-upload

### "Invalid signature" errors
- Verify `META_APP_SECRET` or `WHATSAPP_CUSTOMER_APP_SECRET` matches the app's secret
- The app connected to the flow must match the app secret

### Simulator not working
- Ensure database is migrated (`php artisan migrate`)
- Admin user must exist (`php artisan admin:user-create`)

### Flow not appearing in WhatsApp
- Ensure flow is published
- Interactive mode only works in user-initiated conversations within 24-hour window

### Customer webhook returning 432
- Verify `WHATSAPP_CUSTOMER_APP_SECRET` matches the customer WABA's Meta App Secret
- The customer bot uses a different app secret than the agent bot
