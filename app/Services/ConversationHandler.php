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
    protected MagetsiApiService $magetsi;

    public function __construct(WhatsAppService $whatsapp, MeterValidationService $meterService, MagetsiApiService $magetsi)
    {
        $this->whatsapp = $whatsapp;
        $this->meterService = $meterService;
        $this->magetsi = $magetsi;
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
        $flowToken = $agent->wa_id . ':buy_zesa:' . Str::uuid()->toString();

        // Use data_exchange flow_action so data comes from our endpoint
        $this->whatsapp->sendFlow(
            $agent->wa_id,
            $flowId,
            $flowToken,
            'BUY_ZESA_SCREEN',
            [
                'quick_amounts' => $product['quick_amounts'],
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
        $flowToken = $agent->wa_id . ':settings:' . Str::uuid()->toString();

        // Use data_exchange flow_action so data comes from our endpoint
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
     * Process a completed ZESA purchase flow via Magetsi API.
     *
     * Flow: validate → confirm → process → notify
     */
    public function handleZesaPurchase(Agent $agent, array $data): void
    {
        Log::info('Processing ZESA purchase via Magetsi API', ['agent' => $agent->id, 'data' => $data]);

        $meterNumber = $data['meter_number'] ?? '';
        $amount = $data['amount'] ?? $data['custom_amount'] ?? 0;
        if ($amount === 'other') {
            $amount = $data['custom_amount'] ?? 0;
        }
        $amount = (float) $amount;
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;
        $recipientPhone = $data['recipient_phone'] ?? null;

        // Step 1: Validate meter
        $meterResult = $this->meterService->validate($meterNumber);

        if (! $meterResult['valid']) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Meter Validation Failed*\n\n{$meterResult['error']}"
            );
            $this->sendWelcome($agent);
            return;
        }

        $trace = $meterResult['trace'];
        $currency = $meterResult['currency'] ?? 'USD';
        $recipientName = $meterResult['name'];
        $recipientAddress = $meterResult['address'];
        $recipientCurrency = $meterResult['recipient_currency'] ?? $currency;

        // Find EcoCash debit config
        $ecocashConfig = collect($meterResult['debit'] ?? [])
            ->firstWhere('handler', 'ECOCASH');

        if (! $ecocashConfig) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Payment Error*\n\nEcoCash payment is not available for this meter."
            );
            $this->sendWelcome($agent);
            return;
        }

        $payment = [$this->magetsi->buildEcocashPayment($ecocashNumber, $amount, $currency, $ecocashConfig)];
        $guestId = "Agent {$agent->id}";

        // Step 2: Confirm
        $confirmation = $this->magetsi->confirm(
            'ZESA', $trace, $meterNumber, $amount, $currency,
            $recipientName, $recipientAddress, $recipientCurrency,
            $payment, $guestId,
            $recipientPhone ? ['recipient_phone' => $recipientPhone] : []
        );

        if (! $confirmation['success']) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Confirmation Failed*\n\n{$confirmation['error']}"
            );
            $this->sendWelcome($agent);
            return;
        }

        // Step 3: Process
        $confirmedPayment = $confirmation['payment'] ?? $payment;
        $processPayment = [];
        foreach ($confirmedPayment as $p) {
            $processPayment[] = $this->magetsi->buildEcocashPayment(
                $p['account'] ?? $ecocashNumber,
                $p['amount'] ?? $amount,
                $p['currency'] ?? $currency,
                $ecocashConfig
            );
        }

        $processResult = $this->magetsi->process(
            'ZESA', $trace, $meterNumber, $amount, $currency,
            $recipientName, $recipientAddress, $recipientCurrency,
            $processPayment, $guestId,
            $recipientPhone ? ['recipient_phone' => $recipientPhone] : []
        );

        if (! $processResult['success']) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Transaction Failed*\n\n{$processResult['error']}"
            );
            $this->sendWelcome($agent);
            return;
        }

        $txn = $processResult['transaction'] ?? [];
        $payments = $processResult['payments'] ?? [];

        // Store in local DB
        $transaction = Transaction::create([
            'agent_id' => $agent->id,
            'product_id' => 'zesa',
            'handler' => 'ZESA',
            'meter_number' => $meterNumber,
            'customer_name' => $recipientName,
            'customer_address' => $recipientAddress,
            'amount' => $amount,
            'currency' => $currency,
            'ecocash_number' => $ecocashNumber,
            'recipient_phone' => $recipientPhone,
            'status' => strtolower($txn['status'] ?? 'pending'),
            'trace' => $trace,
            'uid' => $txn['uid'] ?? null,
            'external_uid' => $txn['external_uid'] ?? null,
            'biller_status' => $txn['biller_status'] ?? null,
            'payment_status' => $txn['payment_status'] ?? null,
            'payment_amount' => $txn['payment_amount'] ?? null,
            'customer_reference' => $txn['customer_reference'] ?? null,
            'reference' => $payments[0]['reference'] ?? $txn['uid'] ?? null,
            'api_response' => $processResult,
        ]);

        // Build response text with fee breakdown
        $statusText = ucfirst($txn['status'] ?? 'Processing');
        $ref = $txn['customer_reference'] ?? $transaction->reference ?? '—';

        $feeLines = '';
        foreach ($confirmation['amounts'] ?? [] as $amountInfo) {
            if ($amountInfo['type'] !== 'principal') {
                $feeLines .= "{$amountInfo['name']}: ({$amountInfo['currency']}) {$amountInfo['amount']}\n";
            }
        }

        $smsNote = $recipientPhone ? "\n📱 Token SMS will be sent to {$recipientPhone}" : '';

        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            "✅ *Transaction {$statusText}*\n\n"
            . "Meter: {$meterNumber}\n"
            . "Customer: {$recipientName}\n"
            . "Amount: ({$currency}) {$amount}\n"
            . ($feeLines ? "{$feeLines}" : '')
            . "Ref: {$ref}"
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
                    . "Address: {$result['address']}\n"
                    . "Currency: {$result['currency']}\n\n"
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
}
