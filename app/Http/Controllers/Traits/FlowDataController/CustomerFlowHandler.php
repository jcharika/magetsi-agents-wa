<?php

namespace App\Http\Controllers\Traits\FlowDataController;

use App\Jobs\ProcessAirtimeTransaction;
use App\Jobs\ProcessBundleTransaction;
use App\Jobs\ProcessZesaTransaction;
use App\Models\Agent;
use App\Services\BackendManager;
use App\Services\MeterValidationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait CustomerFlowHandler
{
    abstract protected function backend(): BackendManager;
    abstract protected function meterService(): MeterValidationService;
    abstract protected function buildSuccessResponse(string $flowToken, array $extraParams = []): array;
    abstract protected function parseFlowToken(string $flowToken): array;
    abstract protected function resolveAgent(array $tokenData): Agent;

    protected function handleCustomerInit(string $screen, array $data, Agent $agent): array
    {
        if (!$screen || $screen === 'HOME_SCREEN') {
            return [
                'screen' => 'HOME_SCREEN',
                'data' => [
                    'ecocash_number' => $agent->ecocash_number ?? '',
                ],
            ];
        }

        if ($screen === 'ZESA_SCREEN') {
            return [
                'screen' => 'ZESA_SCREEN',
                'data' => [
                    'ecocash_number' => $agent->ecocash_number ?? '',
                    'meter_valid' => false,
                    'customer_name' => '',
                    'customer_address' => '',
                    'meter_currency' => 'ZWG',
                ],
            ];
        }

        if ($screen === 'AIRTIME_SCREEN') {
            return [
                'screen' => 'AIRTIME_SCREEN',
                'data' => [
                    'ecocash_number' => $agent->ecocash_number ?? '',
                    'networks' => [
                        ['id' => 'econet', 'title' => 'Econet'],
                        ['id' => 'netone', 'title' => 'NetOne'],
                    ],
                ],
            ];
        }

        if ($screen === 'BUNDLES_SCREEN') {
            return [
                'screen' => 'BUNDLES_SCREEN',
                'data' => [
                    'ecocash_number' => $agent->ecocash_number ?? '',
                    'networks' => [
                        ['id' => 'econet', 'title' => 'Econet'],
                        ['id' => 'netone', 'title' => 'NetOne'],
                    ],
                ],
            ];
        }

        return [
            'screen' => 'HOME_SCREEN',
            'data' => $data ?: (object)[],
        ];
    }

    protected function handleCustomerDataExchange(string $screen, array $data, Agent $agent, string $flowToken): array
    {
        if ($screen === 'AIRTIME_SCREEN') {
            return $this->handleBuyAirtime($agent, $data, $flowToken);
        }

        if ($screen === 'BUNDLES_SCREEN') {
            return $this->handleBuyBundle($agent, $data, $flowToken);
        }

        if ($screen === 'ZESA_SCREEN') {
            return $this->handleBuyZesaDataExchange($agent, $data, $flowToken);
        }

        return $this->buildSuccessResponse($flowToken);
    }

    protected function handleBuyZesaDataExchange(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;
        $meterNumber = $data['meter_number'] ?? '';

        if ($trigger === 'verify_meter_number') {
            return $this->verifyMeterNumber($meterNumber);
        }

        if ($trigger === 'buy_zesa') {
            return $this->processZesaPurchase($agent, $data, $flowToken);
        }

        return [
            'screen' => 'ZESA_SCREEN',
            'data' => ['error_message' => 'Please enter a meter number.'],
        ];
    }

    protected function verifyMeterNumber(string $meterNumber): array
    {
        $result = Cache::remember("validation/$meterNumber", 360, function () use ($meterNumber) {
            return $this->meterService()->validate($meterNumber);
        });

        return [
            'screen' => 'ZESA_SCREEN',
            'data' => [
                'meter_valid' => $result['valid'] ?? false,
                'customer_name' => $result['name'] ?? '',
                'customer_address' => $result['address'] ?? '',
                'meter_currency' => $result['currency'] ?? 'ZWG',
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid meter number.',
            ],
        ];
    }

    protected function processZesaPurchase(Agent $agent, array $data, string $flowToken): array
    {
        $meterNumber = $data['meter_number'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;
        $recipientPhone = $data['recipient_phone'] ?? null;

        $params = [
            'type' => 'zesa',
            'meter_number' => $meterNumber,
            'amount' => $amount,
            'currency' => 'ZWG',
            'ecocash_number' => $ecocashNumber,
            'recipient_phone' => $recipientPhone,
            'guest_id' => "Agent {$agent->id}",
        ];

        $agentData = [
            'wa_id' => $agent->wa_id,
            'name' => $agent->name,
            'ecocash_number' => $agent->ecocash_number,
        ];

        ProcessZesaTransaction::dispatch($params, $agentData, $flowToken)
            ->onQueue('transactions');

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'success' => true,
                        'message' => "Your ZESA purchase of {$amount} ZWG for meter {$meterNumber} is being processed. You will receive a WhatsApp notification once complete.",
                        'reference' => 'queued',
                    ],
                ],
            ],
        ];
    }

    protected function handleBuyAirtime(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger !== 'buy_airtime') {
            return [
                'screen' => 'AIRTIME_SCREEN',
                'data' => ['error_message' => 'Invalid action.'],
            ];
        }

        $network = $data['network'] ?? '';
        $phoneNumber = $data['phone_number'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;

        if (!$network || !$phoneNumber || !$amount) {
            return [
                'screen' => 'AIRTIME_SCREEN',
                'data' => ['error_message' => 'Please fill in all required fields.'],
            ];
        }

        $params = [
            'type' => 'airtime',
            'network' => $network,
            'phone_number' => $phoneNumber,
            'amount' => $amount,
            'currency' => 'ZWG',
            'ecocash_number' => $ecocashNumber,
            'guest_id' => "Agent {$agent->id}",
        ];

        $agentData = [
            'wa_id' => $agent->wa_id,
            'name' => $agent->name,
            'ecocash_number' => $agent->ecocash_number,
        ];

        ProcessAirtimeTransaction::dispatch($params, $agentData, $flowToken)
            ->onQueue('transactions');

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'success' => true,
                        'message' => "Your {$network} airtime purchase of {$amount} ZWG for {$phoneNumber} is being processed. You will receive a WhatsApp notification once complete.",
                    ],
                ],
            ],
        ];
    }

    protected function handleBuyBundle(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger !== 'buy_bundle') {
            return [
                'screen' => 'BUNDLES_SCREEN',
                'data' => ['error_message' => 'Invalid action.'],
            ];
        }

        $network = $data['network'] ?? '';
        $phoneNumber = $data['phone_number'] ?? '';
        $bundleSize = $data['bundle_size'] ?? '';
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;

        if (!$network || !$phoneNumber || !$bundleSize) {
            return [
                'screen' => 'BUNDLES_SCREEN',
                'data' => ['error_message' => 'Please fill in all required fields.'],
            ];
        }

        $params = [
            'type' => 'bundle',
            'network' => $network,
            'phone_number' => $phoneNumber,
            'bundle_size' => $bundleSize,
            'currency' => 'ZWG',
            'ecocash_number' => $ecocashNumber,
            'guest_id' => "Agent {$agent->id}",
        ];

        $agentData = [
            'wa_id' => $agent->wa_id,
            'name' => $agent->name,
            'ecocash_number' => $agent->ecocash_number,
        ];

        ProcessBundleTransaction::dispatch($params, $agentData, $flowToken)
            ->onQueue('transactions');

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'success' => true,
                        'message' => "Your {$network} bundle purchase of {$bundleSize} for {$phoneNumber} is being processed. You will receive a WhatsApp notification once complete.",
                    ],
                ],
            ],
        ];
    }
}