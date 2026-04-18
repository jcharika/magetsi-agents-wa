<?php

namespace App\Http\Controllers\Traits\FlowDataController;

use App\Models\Agent;

trait SettingsFlowHandler
{
    abstract protected function buildSuccessResponse(string $flowToken, array $extraParams = []): array;

    protected function initSettings(Agent $agent): array
    {
        $product = $agent->getProductOrDefault('zesa');

        return [
            'screen' => 'SETTINGS_SCREEN',
            'data' => [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'amount_1' => (string)($product['quick_amounts'][0] ?? 100),
                'amount_2' => (string)($product['quick_amounts'][1] ?? 200),
                'amount_3' => (string)($product['quick_amounts'][2] ?? 300),
                'amount_4' => (string)($product['quick_amounts'][3] ?? 500),
            ],
        ];
    }

    protected function handleSettingsExchange(Agent $agent, array $data, string $flowToken): array
    {
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

        return $this->buildSuccessResponse($flowToken, [
            'settings_saved' => true,
            'data' => $data,
        ]);
    }
}