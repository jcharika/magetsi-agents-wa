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
     *
     * Respects the WHATSAPP_FLOW_MODE setting:
     *   - "interactive" → sends an interactive flow message with CTA
     *   - "template"    → sends an approved template with FLOW button
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
        $flowData = [
            'quick_amounts' => $product['quick_amounts'],
            'min_amount' => $product['min_amount'],
            'ecocash_number' => $agent->ecocash_number ?? '',
        ];

        if (config('whatsapp.flow_mode') === 'template') {
            // Template mode — business-initiated, requires approved template
            $this->whatsapp->sendFlowTemplate(
                to: $agent->wa_id,
                templateName: config('whatsapp.flow_templates.buy_zesa', 'buy_zesa_flow'),
                language: config('whatsapp.template_language', 'en'),
                flowToken: $flowToken,
                flowData: $flowData,
                bodyParams: [$agent->name], // {{1}} = agent name
            );
        } else {
            // Interactive mode — within 24h conversation window
            $this->whatsapp->sendFlow(
                $agent->wa_id,
                $flowId,
                $flowToken,
                'BUY_ZESA_SCREEN',
                $flowData,
                'Continue',
                '⚡ Buy ZESA — tap the button below'
            );
        }
    }

    /**
     * Process a completed ZESA purchase flow via active backend.
     *
     * Flow: validate → process → notify
     */
    public function handleZesaPurchase(Agent $agent, array $data): void
    {
        $this->sendWelcome($agent);

        return;

        Log::info('Processing ZESA purchase', ['agent' => $agent->id, 'data' => $data, 'backend' => $this->backend->getBackendName()]);

        $meterNumber = $data['meter_number'] ?? '';
        $amount = $data['amount'] ?? $data['custom_amount'] ?? 0;
        if ($amount === 'other') {
            $amount = $data['custom_amount'] ?? 0;
        }
        $amount = (float) $amount;
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;
        $recipientPhone = $data['recipient_phone'] ?? null;

        // Step 1: Validate meter (backend-agnostic)
        $meterResult = $this->meterService->validate($meterNumber);

        if (! $meterResult['valid']) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Meter Validation Failed*\n\n{$meterResult['error']}"
            );
            $this->sendWelcome($agent);
            return;
        }

        // Step 2: Process transaction (backend-agnostic)
        $result = $this->backend->processTransaction([
            'meter_number' => $meterResult['meter_number'] ?? $meterNumber,
            'amount' => $amount,
            'currency' => $meterResult['currency'] ?? 'USD',
            'ecocash_number' => $ecocashNumber,
            'recipient_name' => $meterResult['name'],
            'recipient_address' => $meterResult['address'],
            'recipient_currency' => $meterResult['recipient_currency'] ?? $meterResult['currency'] ?? 'USD',
            'trace' => $meterResult['trace'] ?? null,
            'debit' => $meterResult['debit'] ?? [],
            'guest_id' => "Agent {$agent->id}",
            'recipient_phone' => $recipientPhone,
        ]);

        if (! $result['success']) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Transaction Failed*\n\n{$result['error']}"
            );
            $this->sendWelcome($agent);
            return;
        }

        $txn = $result['transaction'] ?? [];
        $confirmation = $result['confirmation'] ?? [];
        $currency = $meterResult['currency'] ?? 'USD';

        // Store in local DB
        $transaction = Transaction::create([
            'agent_id' => $agent->id,
            'product_id' => 'zesa',
            'handler' => 'ZESA',
            'meter_number' => $meterNumber,
            'customer_name' => $meterResult['name'],
            'customer_address' => $meterResult['address'],
            'amount' => $amount,
            'currency' => $currency,
            'ecocash_number' => $ecocashNumber,
            'recipient_phone' => $recipientPhone,
            'status' => strtolower($txn['status'] ?? 'pending'),
            'trace' => $meterResult['trace'] ?? null,
            'uid' => $txn['uid'] ?? null,
            'external_uid' => $txn['external_uid'] ?? null,
            'biller_status' => $txn['biller_status'] ?? null,
            'payment_status' => $txn['payment_status'] ?? null,
            'payment_amount' => $txn['payment_amount'] ?? null,
            'customer_reference' => $txn['customer_reference'] ?? null,
            'reference' => $txn['reference'] ?? $txn['uid'] ?? null,
            'api_response' => $result['raw_response'] ?? $result,
        ]);

        // Build response text
        $statusText = ucfirst($txn['status'] ?? 'Processing');
        $ref = $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—';

        $feeLines = '';
        foreach ($confirmation['amounts'] ?? [] as $amountInfo) {
            if (($amountInfo['type'] ?? '') !== 'principal') {
                $feeLines .= "{$amountInfo['name']}: ({$amountInfo['currency']}) {$amountInfo['amount']}\n";
            }
        }

        $smsNote = $recipientPhone ? "\n📱 Token SMS will be sent to {$recipientPhone}" : '';
        $backendLabel = ucfirst($this->backend->getBackendName());

        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            "✅ *Transaction {$statusText}*\n\n"
            . "Backend: {$backendLabel}\n"
            . "Meter: {$meterNumber}\n"
            . "Customer: {$meterResult['name']}\n"
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
