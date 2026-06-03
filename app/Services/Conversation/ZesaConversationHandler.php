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
     * @param Agent $agent
     * @param string|null $meterNumber Pre-fill meter number if provided
     * @param string|null $buttonText Custom button text (default: "Continue")
     * @param string|null $message
     */
    public function launchBuyZesaFlow(Agent $agent, ?string $meterNumber = null, ?string $buttonText = null, ?string $message = null): void
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

        // Pre-fill meter number if provided
        if ($meterNumber) {
            $flowData['meter_number'] = $meterNumber;
            $flowData['meter_valid'] = true;
        }

        $ctaText = $buttonText ?? 'Continue';

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
                $ctaText,
                $message ?? '⚡ Buy ZESA — tap the button below'
            );
        }
    }
}
