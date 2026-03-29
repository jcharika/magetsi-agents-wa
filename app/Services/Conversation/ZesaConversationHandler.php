<?php

namespace App\Services\Conversation;

use App\Models\Agent;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait ZesaConversationHandler
{
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
}
