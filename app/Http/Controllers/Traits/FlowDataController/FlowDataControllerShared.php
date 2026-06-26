<?php

namespace App\Http\Controllers\Traits\FlowDataController;

use App\Models\Agent;
use App\Models\Customer;

trait FlowDataControllerShared
{
    protected function buildSuccessResponse(string $flowToken, array $extraParams = []): array
    {
        return [
            'screen' => [
                "type" => "success",
                "title" => "Thank You",
                'data' => [
                    'extension_message_response' => [
                        'params' => array_merge(
                            ['flow_token' => $flowToken],
                            $extraParams
                        ),
                    ],
                ],
            ],
        ];
    }

    /**
     * Wrap a data_exchange handler result for the WhatsApp Flows endpoint.
     *
     * WhatsApp Flows expects data_exchange responses in the format:
     *   {"response": {"screen": "...", "data": {...}}}
     *
     * Handlers internally return ["screen" => ..., "data" => ...]; this helper
     * normalises the payload and ensures "data" is always encoded as a JSON object.
     * Completion responses (success/failure screens or extension messages) are
     * returned unchanged.
     */
    protected function wrapDataExchangeResponse(array $payload): array
    {
        $isCompletion = (isset($payload['screen']) && is_array($payload['screen']) && isset($payload['screen']['type']))
            || isset($payload['data']['extension_message_response']);

        if ($isCompletion) {
            return $payload;
        }

        $data = $payload['data'] ?? [];

        if (is_array($data) && empty($data)) {
            $data = (object)[];
        }

        $response = ['data' => $data];

        if (isset($payload['screen']) && is_string($payload['screen'])) {
            $response['screen'] = $payload['screen'];
        }

        return ['response' => $response];
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

    protected function resolveCustomerFromToken(array $tokenData): Customer
    {
        $waId = $tokenData['wa_id'] ?? '';

        return Customer::firstOrCreate(
            ['wa_id' => $waId],
            ['name' => null, 'phone' => null],
        );
    }
}
