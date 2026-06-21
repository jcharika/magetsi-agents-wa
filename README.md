# Magetsi Agents

A Laravel 13-based WhatsApp chatbot that enables agents (resellers) to purchase ZESA electricity tokens via the WhatsApp Cloud API. Supports rich interactive forms (WhatsApp Flows), text-based onboarding, and dual transaction backends.

## Features

- **WhatsApp Webhook** — Receive and process incoming text, button, and flow completion messages
- **WhatsApp Flows** — Rich native forms for Buy ZESA and Settings with end-to-end encryption (RSA + AES-128-GCM)
- **Dual Backend Support** — Swappable between new API (`magetsi.co.zw`) and legacy API (`legacy.magetsi.co.zw`)
- **Text Onboarding** — Multi-step conversational flow for new agents with auto-activation
- **Meter Validation** — Live ZESA meter number validation against the active backend
- **Browser Simulator** — Full WhatsApp-like UI for local development without Meta setup
- **Automated Deployment** — `php artisan whatsapp:configure` for one-command Meta setup

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan whatsapp:generate-keypair
npm install && npm run build
php artisan serve --port=8111
```

Visit `http://localhost:8111/` to open the simulator.

## Documentation

| Document | Description |
|---|---|
| [docs/developer-setup.md](docs/developer-setup.md) | Complete developer setup guide |
| [docs/features.md](docs/features.md) | Feature documentation |
| [docs/deployment.md](docs/deployment.md) | Production deployment guide |
| [docs/whatsapp-flows-setup.md](docs/whatsapp-flows-setup.md) | WhatsApp Flows encryption setup |

## Architecture

```
WhatsApp Client → POST /api/webhook → ConversationHandler → BackendManager → Magetsi API
                → POST /api/flow-data → FlowDataController (encrypted) → BackendManager
                → GET  /api/webhook  → Verify webhook challenge
```

## Tech Stack

- **Framework:** Laravel 13
- **PHP:** 8.3+
- **WhatsApp:** Cloud API v25.0, Flows (data_api_version 3.0)
- **Encryption:** phpseclib 3 (RSA OAEP SHA-256) + AES-128-GCM
- **Frontend:** Tailwind CSS 4, Vite 8
- **Database:** SQLite (default)
- **Deployment:** Deployer 7

All payment methods
Gap analysis for the chatbot
