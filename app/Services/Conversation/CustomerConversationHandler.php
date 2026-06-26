<?php

namespace App\Services\Conversation;

use App\Models\Agent;
use App\Models\Transaction;
use App\Http\Controllers\Traits\UsesCustomerFlow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait CustomerConversationHandler
{
    use UsesCustomerFlow;

    public function launchCustomerFlow(Agent $agent, ?string $buttonText = null, ?string $message = null): void
    {
        $flowId = $this->getCustomerFlowId();

        if (! $flowId) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "🛒 *Magetsi Shop*\n\nWelcome! How can I help you today?\n\n• Buy ZESA tokens\n• Buy airtime\n• Buy data bundles"
            );
            return;
        }

        $product = $agent->getProductOrDefault('zesa');
        $flowToken = $agent->wa_id . ':customer:' . Str::uuid()->toString();
        $flowData = [
            'quick_amounts' => $product['quick_amounts'],
            'min_amount' => $product['min_amount'],
            'ecocash_number' => $agent->ecocash_number ?? '',
        ];

        $ctaText = $buttonText ?? 'Shop Now';

        if (config('whatsapp.flow_mode') === 'template') {
            $this->whatsapp->sendFlowTemplate(
                to: $agent->wa_id,
                templateName: config('whatsapp.flow_templates.customer', 'customer_flow'),
                language: config('whatsapp.template_language', 'en'),
                flowToken: $flowToken,
                flowData: $flowData,
                bodyParams: [$agent->name],
            );
        } else {
            $this->whatsapp->sendFlow(
                $agent->wa_id,
                $flowId,
                $flowToken,
                null,
                null,
                $ctaText,
                $message ?? '🛒 Magetsi Shop — tap to continue'
            );
        }
    }
}