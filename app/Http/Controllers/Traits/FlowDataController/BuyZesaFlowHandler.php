<?php

namespace App\Http\Controllers\Traits\FlowDataController;

use App\Models\Agent;
use App\Services\BackendManager;
use App\Services\MeterValidationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait BuyZesaFlowHandler
{
    abstract protected function meterService(): MeterValidationService;
    abstract protected function backend(): BackendManager;
    abstract protected function buildSuccessResponse(string $flowToken, array $extraParams = []): array;
    abstract protected function parseFlowToken(string $flowToken): array;
    abstract protected function resolveAgent(array $tokenData): Agent;

    protected function initBuyZesa(Agent $agent): array
    {
        $product = $agent->getProductOrDefault('zesa');

        return [
            'screen' => 'BUY_ZESA_SCREEN',
            'data' => [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'currency' => $product['currency'] ?? 'ZWG',
                'min_amount' => $product['min_amount'] ?? 100,
                'quick_amounts' => $product['quick_amounts'] ?? [100, 200, 300, 500],
            ],
        ];
    }

    protected function handleBuyZesaExchange(Agent $agent, array $data, string $flowToken): array
    {
        $meterNumber = $data['meter_number'] ?? '';
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'buy_zesa') {
            return $this->processZesaTransaction($agent, $data, $flowToken);
        }

        if ($trigger === 'verify_meter_number') {
            $result = Cache::remember("validation/$meterNumber", 360, function () use ($meterNumber) {
                return $this->meterService()->validate($meterNumber);
            });

            if ($result['valid']) {
                return [
                    'screen' => 'BUY_ZESA_SCREEN',
                    'data' => [
                        'meter_valid' => true,
                        'customer_name' => 'Meter Name: **' . ($result['name'] ?? '') . '**',
                        'customer_address' => 'Address: **' . ($result['address'] ?? '') . '**',
                        'meter_currency' => 'Meter Currency: **' . ($result['currency'] ?? '') . '**',
                        'error_message' => '',
                    ],
                ];
            }

            return [
                'screen' => 'BUY_ZESA_SCREEN',
                'data' => [
                    'meter_valid' => false,
                    'customer_name' => '',
                    'customer_address' => '',
                    'error_message' => $result['error'] ?? 'Invalid meter number.',
                ],
            ];
        }

        return [
            'screen' => 'BUY_ZESA_SCREEN',
            'data' => [
                'error_message' => 'Please enter a meter number.',
            ],
        ];
    }

    protected function processZesaTransaction(Agent $agent, array $data, string $flowToken): array
    {
        $meterNumber = $data['meter_number'];
        $amount = $data['amount'] === 'other'
            ? (float)($data['custom_amount'] ?? 0)
            : (float)$data['amount'];
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;
        $recipientPhone = $data['recipient_phone'] ?? null;

        try {
            $meterResult = Cache::get("validation/$meterNumber");
            $result = $this->backend()->processTransaction([
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

            if (!$result['success']) {
                return [
                    'screen' => 'BUY_ZESA_SCREEN',
                    'data' => ['error_message' => $result['error'] ?? 'Transaction processing failed.'],
                ];
            }

            $txn = $result['transaction'] ?? [];

            return $this->buildSuccessResponse($flowToken, [
                'meter_number' => $meterNumber,
                'customer_name' => $meterResult['name'],
                'amount' => $amount,
                'currency' => $meterResult['currency'] ?? 'USD',
                'status' => $txn['status'] ?? 'PENDING',
                'reference' => $txn['customer_reference'] ?? $txn['reference'] ?? $txn['uid'] ?? '',
                'trace' => $meterResult['trace'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            Log::error($exception->getMessage());

            return [
                'screen' => 'BUY_ZESA_SCREEN',
                'data' => ['error_message' => $exception->getMessage() ?: 'Transaction processing failed.'],
            ];
        }
    }
}