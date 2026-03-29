# WhatsApp Flows Encryption — Setup Guide

This guide covers the end-to-end setup for WhatsApp Flows with encrypted data exchange.

## Architecture Overview

```
WhatsApp Client
     │
     ▼ (encrypted request)
┌──────────────────────────────┐
│  POST /api/flow-data         │
│  FlowDataController          │
│                              │
│  1. Verify X-Hub-Signature   │
│  2. Decrypt (RSA + AES-GCM)  │
│  3. Route: INIT/data_exchange │
│  4. Process business logic    │
│  5. Encrypt response          │
│  6. Return base64 plaintext   │
└──────────────────────────────┘
     │
     ▼ (calls Magetsi API)
┌──────────────────────────────┐
│  magetsi.test                │
│  prepare → validate →        │
│  confirm → process           │
└──────────────────────────────┘
```

## Step 1: Generate RSA Key Pair

Run the artisan command:

```bash
php artisan whatsapp:generate-keypair
```

This creates:
- `storage/app/whatsapp/private.pem` — **keep secret**, used for decryption
- `storage/app/whatsapp/public.pem` — upload to Meta

If regenerating, use `--force`:

```bash
php artisan whatsapp:generate-keypair --force
```

## Step 2: Upload Public Key to Meta

Upload the public key using the Cloud API:

```bash
# Read your public key
PUBLIC_KEY=$(cat storage/app/whatsapp/public.pem)

# Upload to Meta
curl -X POST \
  "https://graph.facebook.com/v21.0/<PHONE_NUMBER_ID>/whatsapp_business_encryption" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "business_public_key=$PUBLIC_KEY"
```

Replace:
- `<PHONE_NUMBER_ID>` — your WhatsApp phone number ID (from `.env`)
- `<ACCESS_TOKEN>` — your WhatsApp business token (from `.env`)

You should receive:

```json
{"success": true}
```

## Step 3: Configure Environment

Update `.env`:

```env
# The private key file path
WHATSAPP_FLOW_PRIVATE_KEY_PATH=/path/to/storage/app/whatsapp/private.pem

# Meta App Secret (from App Dashboard → Settings → Basic → App Secret)
META_APP_SECRET=your_app_secret_here

# Flow IDs (set after creating flows in Step 4)
WHATSAPP_BUY_ZESA_FLOW_ID=
WHATSAPP_SETTINGS_FLOW_ID=
```

> **Alternative:** Instead of a file path, you can paste the private key inline:
> ```env
> WHATSAPP_FLOW_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIE...\n-----END PRIVATE KEY-----"
> ```

## Step 4: Create Flows in Meta WhatsApp Manager  

### Option A: Via the Flows API

```bash
# Create Buy ZESA flow
curl -X POST \
  "https://graph.facebook.com/v21.0/<WABA_ID>/flows" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -F "name=Buy ZESA Tokens" \
  -F "categories=[\"OTHER\"]"
```

This returns a flow ID. Set it in `.env`:
```env
WHATSAPP_BUY_ZESA_FLOW_ID=<returned_flow_id>
```

Then upload the flow JSON:

```bash
curl -X POST \
  "https://graph.facebook.com/v21.0/<BUY_ZESA_FLOW_ID>/assets" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -F "name=flow.json" \
  -F "file=@resources/flows/buy_zesa.json"
```

Repeat for Settings:

```bash
curl -X POST \
  "https://graph.facebook.com/v21.0/<WABA_ID>/flows" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -F "name=Agent Settings" \
  -F "categories=[\"OTHER\"]"
```

```bash
curl -X POST \
  "https://graph.facebook.com/v21.0/<SETTINGS_FLOW_ID>/assets" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -F "name=flow.json" \
  -F "file=@resources/flows/settings.json"
```

### Option B: Via WhatsApp Manager UI

1. Go to **WhatsApp Manager** → **Account tools** → **Flows**
2. Click **Create Flow** → Name: "Buy ZESA Tokens" → Category: "Other"
3. In the editor, switch to JSON mode and paste contents of `resources/flows/buy_zesa.json`
4. Note the Flow ID and set `WHATSAPP_BUY_ZESA_FLOW_ID` in `.env`
5. Repeat for "Agent Settings" with `resources/flows/settings.json`

## Step 5: Configure Flow Endpoint

For each flow, set the endpoint URL:

```bash
# Set endpoint URL for Buy ZESA
curl -X POST \
  "https://graph.facebook.com/v21.0/<BUY_ZESA_FLOW_ID>" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"endpoint_uri": "https://your-domain.com/api/flow-data"}'

# Set endpoint URL for Settings
curl -X POST \
  "https://graph.facebook.com/v21.0/<SETTINGS_FLOW_ID>" \
  -H "Authorization: Bearer <ACCESS_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"endpoint_uri": "https://your-domain.com/api/flow-data"}'
```

**The endpoint must be HTTPS with a valid SSL certificate.**

## Step 6: Publish Flows

Flows must be published before they can be used in production:

```bash
curl -X POST \
  "https://graph.facebook.com/v21.0/<FLOW_ID>/publish" \
  -H "Authorization: Bearer <ACCESS_TOKEN>"
```

## Step 7: Configure Webhook

Set the webhook URL in Meta Business Manager:

1. Go to **App Dashboard** → **WhatsApp** → **Configuration**
2. Set **Webhook URL** to: `https://your-domain.com/api/webhook`
3. Set **Verify token** to the value in your `.env` (`WHATSAPP_VERIFY_TOKEN`)
4. Subscribe to: `messages`

## Encryption Details

### How It Works

The data exchange uses a combination of RSA and AES-GCM:

1. **Request Decryption:**
   - WhatsApp sends `{encrypted_flow_data, encrypted_aes_key, initial_vector}`
   - The AES key is encrypted with your RSA public key (OAEP/SHA-256/MGF1-SHA-256)
   - The flow data is encrypted with AES-128-GCM using the AES key + IV
   - The last 16 bytes of `encrypted_flow_data` are the GCM auth tag

2. **Response Encryption:**
   - Flip all bits of the original IV (`byte ^ 0xFF`)
   - Encrypt the JSON response with AES-128-GCM using the same AES key + flipped IV
   - Append the GCM auth tag, base64-encode, return as plain text

### Signature Verification

Meta signs every request with `X-Hub-Signature-256` using your App Secret:

```
sha256=HMAC-SHA256(request_body, app_secret)
```

If verification fails, the endpoint returns HTTP 432.

### Error Handling

| HTTP Status | Meaning |
|---|---|
| `200` | Success — encrypted response in body |
| `400` | Missing encryption fields |
| `421` | Decryption failed — client re-downloads public key |
| `432` | Signature verification failed |

## Re-uploading the Public Key

You must re-upload the public key when:

- You re-register your phone number
- You migrate between On-Prem and Cloud API
- You receive `public-key-missing` or `public-key-signature-verification` webhook alerts

```bash
php artisan whatsapp:generate-keypair --force
# Then upload the new public key (Step 2)
```

## Troubleshooting

### "Decryption failed" errors
- Verify your private key matches the uploaded public key
- Check that `WHATSAPP_FLOW_PRIVATE_KEY_PATH` points to the correct file
- Ensure `phpseclib/phpseclib` is installed (`composer require phpseclib/phpseclib:^3.0`)

### "Invalid signature" errors
- Verify `META_APP_SECRET` matches your app's secret in Meta App Dashboard
- The app connected to the flow must match the app secret you're using

### Health check failures
- Ensure the endpoint is publicly accessible via HTTPS
- The endpoint responds to action `ping` with `{"data": {"status": "active"}}`
