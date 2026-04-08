<?php

namespace App\Services\Conversation;

use App\Models\Agent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait SettingsConversationHandler
{
    /**
     * Launch the Settings flow.
     *
     * Respects the WHATSAPP_FLOW_MODE setting:
     *   - "interactive" → sends an interactive flow message with CTA
     *   - "template"    → sends an approved template with FLOW button
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
        $flowData = [
            'ecocash_number' => $agent->ecocash_number ?? '',
            'amount_1' => (string) ($product['quick_amounts'][0] ?? 100),
            'amount_2' => (string) ($product['quick_amounts'][1] ?? 200),
            'amount_3' => (string) ($product['quick_amounts'][2] ?? 300),
            'amount_4' => (string) ($product['quick_amounts'][3] ?? 500),
        ];

        if (config('whatsapp.flow_mode') === 'template') {
            // Template mode — business-initiated, requires approved template
            $this->whatsapp->sendFlowTemplate(
                to: $agent->wa_id,
                templateName: config('whatsapp.flow_templates.settings', 'settings_flow'),
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
                'SETTINGS_SCREEN',
                $flowData,
                '⚙️ Settings',
                '⚙️ Settings — tap the button to update your preferences'
            );
        }
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

        $this->whatsapp->sendTextMessage(
            $agent->wa_id,
            "✅ *Settings Saved!*\n\nYour preferences have been updated."
        );

        // Send the menu as direct flow CTAs
        $this->launchBuyZesaFlow($agent);
    }
}
