# Features

Magetsi Agents is a Laravel 13-based WhatsApp chatbot that enables agents (resellers) to purchase ZESA electricity tokens via the WhatsApp Cloud API. It supports two transaction backends, WhatsApp Flows for rich interactive forms, a text-based onboarding flow, and a browser-based simulator for development.

---

## 1. WhatsApp Webhook

Receives and processes incoming messages from the WhatsApp Cloud API.

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/webhook` | GET | Webhook verification (Meta challenge) |
| `/api/webhook` | POST | Incoming messages (text, buttons, flows) |

**Message routing:**
- `text` — Natural language input routed to `ConversationHandler`
- `interactive.button_reply` — Button press routed by button ID
- `interactive.list_reply` — List selection routed by list ID
- `interactive.nfm_reply` — Flow completion response decoded and routed

---

## 2. WhatsApp Flows (Rich Interactive Forms)

Instead of multi-turn text conversations, agents complete purchases through native WhatsApp forms.

### Buy ZESA Flow
- **Screen:** `BUY_ZESA_SCREEN` — single-screen, terminal
- **Fields:** meter number (11 digits), amount, EcoCash number, optional recipient phone (SMS delivery)
- **Initial data:** Agent's saved EcoCash number, quick amounts, currency, minimum amount
- **Trigger:** Inline flow message via `sendFlow()` or template-based via `sendFlowTemplate()`

### Settings Flow
- **Screen:** `SETTINGS_SCREEN` — single-screen, terminal
- **Fields:** EcoCash number (pre-filled), up to 4 quick amounts
- **Purpose:** Update default payment number and preset amounts for the Buy ZESA flow
- **Trigger:** Same as Buy ZESA (interactive or template)

### Flow Modes
Configured via `WHATSAPP_FLOW_MODE`:
- **`interactive`** (default) — Uses `interactive` messages with inline flow data. No pre-approval needed.
- **`template`** — Uses pre-approved message templates with FLOW buttons. Required for business-initiated conversations.

---

## 3. Flow Encryption & Security

End-to-end encryption for WhatsApp Flows data exchange, implementing Meta's `data_api_version: "3.0"`.

**Endpoint:** `POST /api/flow-data`

### Encryption Flow
```
WhatsApp Client
     │ (encrypted request)
     ▼
POST /api/flow-data
  │ 1. Verify X-Hub-Signature-256 (HMAC-SHA256)
  │ 2. Decrypt AES key via RSA OAEP SHA-256 (phpseclib3)
  │ 3. Decrypt payload via AES-128-GCM (last 16 bytes = auth tag)
  │ 4. Route action: ping / INIT / BACK / data_exchange
  │ 5. Encrypt response (IV bit-flip + AES-128-GCM)
  │ 6. Return base64 plaintext
```

### Error Codes
| HTTP Status | Meaning |
|---|---|
| `200` | Success — encrypted response in body |
| `400` | Missing encryption fields |
| `421` | Decryption failed — client re-downloads public key |
| `432` | Signature verification failed |

### Key Management
- Generate keys: `php artisan whatsapp:generate-keypair`
- Stores `private.pem` (secret, decryption) and `public.pem` (uploaded to Meta)
- Alternative: inline private key via `WHATSAPP_FLOW_PRIVATE_KEY` env var

---

## 4. Meter Validation

Validates ZESA meter numbers against the active backend before proceeding with a transaction.

- **11-digit format** enforced for legacy backend
- Returns customer name, address, and supported currencies
- Validated both via the simulator (with debounced live API call) and in the flow `data_exchange`

---

## 5. Transaction Processing

Supports two backend implementations switchable via `MAGETSI_BACKEND`.

### New API (`magetsi.test`) — 4-step lifecycle
```
prepare → validate → confirm → process
```
1. **prepare** — Get trace ID and payment method options
2. **validate** — Validate meter number, get customer info
3. **confirm** — Get fee breakdown (discounts, service fees, loyalty)
4. **process** — Execute transaction, get reference and token

### Legacy API (`magetsi.co.zw`) — 2-step lifecycle
```
check meter → init + poll
```
1. **check meter** — Validate 11-digit meter number
2. **init** — Submit transaction, get reference
3. **poll** — Poll for completion (configurable attempts/interval)

### Payment Methods Supported
| Method | Currencies | Requires Account | Requires Auth |
|---|---|---|---|
| Cash | ZWG, USD | No | Yes |
| Bank | ZWG, USD | No | Yes |
| EcoCash | ZWG, USD | Yes (phone) | No |
| International Card | USD | No | No |
| Wallet | ZWG, USD, LTY | Yes | No |
| Manual | ZWG, USD, LTY | No | Yes |

---

## 6. Text-Based Onboarding Flow

Multi-step conversational flow for new agents (auto-activates when `agent.needsOnboarding()` is true).

**Steps:**
1. **name** — Asks for first name (letters only, 2–30 chars)
2. **ecocash** — Asks for EcoCash number (10–12 digits)

On completion: saves name, EcoCash number, sets `onboarded = true`, then sends welcome with flow options.

Powered by a generic **FlowEngine** that auto-discovers `BotFlow` classes in `app/Bot/` and handles step progression, validation, transformation, and persistence.

---

## 7. Conversation Routing (Text Chat)

Natural language processing via `ConversationHandler` with keyword routing:

| Input | Action |
|---|---|
| `hi`, `hello`, `hey`, `start`, `menu` | Send welcome message with flow options |
| `zesa`, `buy`, `token` | Launch Buy ZESA flow |
| `settings`, `preferences` | Launch Settings flow |
| 11-digit number | Validate meter number directly |
| _(other)_ | Show welcome menu |

A **ConversationSession** (cache-backed, 1-hour TTL) tracks active text-based flow position for multi-step interactions.

---

## 8. Browser-Based Simulator

A standalone WhatsApp Web simulator for local development, accessible at `/`.

**Features:**
- WhatsApp-like chat UI (bubbles, header, input bar)
- Text messaging, reply buttons, flow CTA buttons
- Generic flow renderer that dynamically reads Flow JSON schemas and renders form components
- Meter validation with debounced (1200ms) live API call
- Flow completion with success card display
- Support for all interactive button and list reply types

**Routes (development only):**
| Method | URI | Purpose |
|---|---|---|
| GET | `/` | Simulator UI |
| POST | `/simulate` | Simulate WhatsApp messages |
| GET | `/simulate/flow/{flowId}` | Flow JSON schema with initial data |

---

## 9. WhatsApp Configuration Command

`php artisan whatsapp:configure` — Automates Meta Business Platform setup:
- Creates flows via Graph API
- Uploads flow JSON assets
- Publishes flows
- Creates message templates with FLOW buttons
- Updates `.env` with new flow IDs

Flags: `--flows`, `--templates`, `--publish`, `--endpoint=`, `--dry-run`

---

## 10. Data Model

| Entity | Table | Purpose |
|---|---|---|
| **Agent** | `agents` | WhatsApp agent with name, phone, EcoCash number, wa_id, onboarded status |
| **AgentProduct** | `agent_products` | Per-agent product configuration (currency, min amount, 4 quick amounts) |
| **Transaction** | `transactions` | Transaction log with full lifecycle (status, token, reference, trace, API response) |
| **User** | `users` | Admin users (Laravel auth, Sanctum tokens) |

---

## 11. Backend Comparison

| Aspect | New API | Legacy API |
|---|---|---|
| Base URL | `magetsi.test` | `magetsi.co.zw` |
| Steps | 4 (prepare, validate, confirm, process) | 2 (check, init + poll) |
| Auth | Channel + handler | Token + email |
| Polling | Synchronous (single call) | Asynchronous (poll for completion) |
| Config | `MAGETSI_API_URL`, `MAGETSI_CHANNEL` | `MAGETSI_LEGACY_URL`, `MAGETSI_LEGACY_TOKEN` |
| Switch | `MAGETSI_BACKEND=new` | `MAGETSI_BACKEND=legacy` |
