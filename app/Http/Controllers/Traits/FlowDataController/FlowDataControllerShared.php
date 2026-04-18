<?php

namespace App\Http\Controllers\Traits\FlowDataController;

use App\Models\Agent;

trait FlowDataControllerShared
{
    protected function buildSuccessResponse(string $flowToken, array $extraParams = []): array
    {
        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => array_merge(
                        ['flow_token' => $flowToken],
                        $extraParams
                    ),
                ],
            ],
        ];
    }

    protected function parseFlowToken(string $flowToken): array
    {
        $parts = explode(':', $flowToken, 3);

        return [
            'wa_id' => $parts[0] ?? '',
            'flow' => $parts[1] ?? '',
            'session' => $parts[2] ?? $flowToken,
        ];
    }

    protected function resolveAgent(array $tokenData): Agent
    {
        if (!empty($tokenData['wa_id'])) {
            $agent = Agent::where('wa_id', $tokenData['wa_id'])->first();
            if ($agent) {
                return $agent;
            }
        }

        return Agent::firstOrCreate(
            ['phone' => $tokenData['wa_id']],
            ['name' => 'Tinashe', 'wa_id' => '263771234567', 'ecocash_number' => '0771234567']
        );
    }
}