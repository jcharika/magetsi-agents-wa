<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConversationHandler
{
    protected WhatsAppService $whatsapp;
    protected MeterValidationService $meterService;

    public function __construct(WhatsAppService $whatsapp, MeterValidationService $meterService)
    {
        $this->whatsapp = $whatsapp;
        $this->meterService = $meterService;
    }

    /**
     * Find or create an agent from a WhatsApp message.
     */
    public function resolveAgent(string $waId, ?string $name = null): Agent
    {
        return Agent::query()->firstOrCreate(
            ['wa_id' => $waId],
            [
                'name' => $name ?? 'Agent',
                'phone' => $waId,
            ]
        );
    }

    /**
     * Send the welcome message with interactive buttons.
     */
    public function sendWelcome(Agent $agent): void
    {
        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            "👋 Welcome back, *{$agent->name}*!\nUse the buttons below to buy ZESA or update your settings.",
            [
                ['id' => 'buy_zesa', 'title' => '⚡ Buy ZESA'],
                ['id' => 'settings', 'title' => '⚙️ Settings'],
            ],
            'Magetsi Agents'
        );
    }

    /**
     * Launch the Buy ZESA flow.
     */
    public function launchBuyZesaFlow(Agent $agent): void
    {
        $flowId = config('whatsapp.flows.buy_zesa');

        if (! $flowId) {
            // Fallback: send text instructions if flow not configured
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "⚡ *Buy ZESA Tokens*\n\nPlease send your meter number (11 digits) to get started."
            );
            return;
        }

        $product = $agent->getProductOrDefault('zesa');
        $flowToken = Str::uuid()->toString();

        $this->whatsapp->sendFlow(
            $agent->wa_id,
            $flowId,
            $flowToken,
            'BUY_ZESA_SCREEN',
            [
                'quick_amounts' => $product['quick_amounts'],
                'currency' => $product['currency'],
                'min_amount' => $product['min_amount'],
                'ecocash_number' => $agent->ecocash_number ?? '',
            ],
            '⚡ Buy ZESA'
        );
    }

    /**
     * Launch the Settings flow.
     */
    public function launchSettingsFlow(Agent $agent): void
    {
        $flowId = config('whatsapp.flows.settings');

        if (! $flowId) {
            // Fallback: send text settings
            $product = $agent->getProductOrDefault('zesa');
            $amounts = implode(', ', $product['quick_amounts']);

            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "⚙️ *Your Settings*\n\n"
                . "EcoCash: {$agent->ecocash_number}\n"
                . "Quick Amounts: {$amounts}\n\n"
                . "To update your settings, please use the settings flow when configured."
            );
            return;
        }

        $product = $agent->getProductOrDefault('zesa');
        $flowToken = Str::uuid()->toString();

        $this->whatsapp->sendFlow(
            $agent->wa_id,
            $flowId,
            $flowToken,
            'SETTINGS_SCREEN',
            [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'amount_1' => (string) ($product['quick_amounts'][0] ?? 100),
                'amount_2' => (string) ($product['quick_amounts'][1] ?? 200),
                'amount_3' => (string) ($product['quick_amounts'][2] ?? 300),
                'amount_4' => (string) ($product['quick_amounts'][3] ?? 500),
            ],
            '⚙️ Settings'
        );
    }

    /**
     * Process a completed ZESA purchase flow.
     */
    public function handleZesaPurchase(Agent $agent, array $data): void
    {
        Log::info('Processing ZESA purchase', ['agent' => $agent->id, 'data' => $data]);

        $meterNumber = $data['meter_number'] ?? '';
        $amount = $data['amount'] ?? $data['custom_amount'] ?? 0;
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;
        $recipientPhone = $data['recipient_phone'] ?? null;

        // Validate meter
        $meterResult = $this->meterService->validate($meterNumber);

        if (! $meterResult['valid']) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Meter Validation Failed*\n\n{$meterResult['error']}"
            );
            return;
        }

        // Create transaction
        $transaction = Transaction::create([
            'agent_id' => $agent->id,
            'product_id' => 'zesa',
            'meter_number' => $meterNumber,
            'customer_name' => $meterResult['name'],
            'customer_address' => $meterResult['address'],
            'amount' => (int) $amount,
            'currency' => 'ZWG',
            'ecocash_number' => $ecocashNumber,
            'recipient_phone' => $recipientPhone,
            'status' => 'pending',
        ]);

        // Simulate EcoCash payment + token generation
        $token = $this->generateSimulatedToken();
        $transaction->update([
            'status' => 'completed',
            'token' => $token,
            'reference' => 'EC' . rand(100000, 999999),
        ]);

        // Send success message
        $smsNote = $recipientPhone
            ? "\n📱 Token SMS sent to {$recipientPhone}"
            : '';

        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            "✅ *Transaction Successful*\n\n"
            . "Meter: {$meterNumber}\n"
            . "Customer: {$meterResult['name']}\n"
            . "Amount: ZWG {$amount}\n"
            . "Token: `{$token}`\n"
            . "Ref: {$transaction->reference}"
            . $smsNote,
            [
                ['id' => 'buy_zesa', 'title' => '⚡ New Transaction'],
                ['id' => 'settings', 'title' => '⚙️ Settings'],
            ]
        );
    }

    /**
     * Process a completed settings flow.
     */
    public function handleSettingsUpdate(Agent $agent, array $data): void
    {
        Log::info('Updating settings', ['agent' => $agent->id, 'data' => $data]);

        if (isset($data['ecocash_number']) && $data['ecocash_number']) {
            $agent->update(['ecocash_number' => $data['ecocash_number']]);
        }

        // Update quick amounts if provided
        $amounts = array_filter([
            $data['amount_1'] ?? null,
            $data['amount_2'] ?? null,
            $data['amount_3'] ?? null,
            $data['amount_4'] ?? null,
        ]);

        if (count($amounts) === 4) {
            $agent->products()->updateOrCreate(
                ['product_id' => 'zesa'],
                [
                    'label' => 'ZESA Tokens',
                    'icon' => '⚡',
                    'currency' => 'ZWG',
                    'min_amount' => 100,
                    'quick_amounts' => array_map('intval', $amounts),
                ]
            );
        }

        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            "✅ *Settings Saved!*\n\nYour preferences have been updated.",
            [
                ['id' => 'buy_zesa', 'title' => '⚡ Buy ZESA'],
                ['id' => 'settings', 'title' => '⚙️ Settings'],
            ]
        );
    }

    /**
     * Handle a text message from the agent.
     */
    public function handleTextMessage(Agent $agent, string $text): void
    {
        $normalized = strtolower(trim($text));

        if (in_array($normalized, ['hi', 'hello', 'hey', 'start', 'menu'])) {
            $this->sendWelcome($agent);
            return;
        }

        // Check if it looks like a meter number (11 digits)
        if (preg_match('/^\d{11}$/', $normalized)) {
            $result = $this->meterService->validate($normalized);

            if ($result['valid']) {
                $this->whatsapp->sendTextMessage(
                    $agent->wa_id,
                    "✅ *Meter Found*\n\n"
                    . "Name: {$result['name']}\n"
                    . "Address: {$result['address']}\n\n"
                    . "Use the *Buy ZESA* button to purchase tokens."
                );
            } else {
                $this->whatsapp->sendTextMessage(
                    $agent->wa_id,
                    "❌ {$result['error']}"
                );
            }

            $this->sendWelcome($agent);
            return;
        }

        // Default: show welcome
        $this->sendWelcome($agent);
    }

    /**
     * Handle an interactive button reply.
     */
    public function handleButtonReply(Agent $agent, string $buttonId): void
    {
        match ($buttonId) {
            'buy_zesa' => $this->launchBuyZesaFlow($agent),
            'settings' => $this->launchSettingsFlow($agent),
            default => $this->sendWelcome($agent),
        };
    }

    /**
     * Generate a simulated ZESA token.
     */
    protected function generateSimulatedToken(): string
    {
        return implode('-', [
            str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
        ]);
    }
}
