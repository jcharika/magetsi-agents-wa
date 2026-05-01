<?php

namespace App\Services;

use App\Contracts\TransactionBackend;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MockMagetsiService implements TransactionBackend
{
    public function getBackendName(): string
    {
        return 'Mock Backend (Testing)';
    }

    public function validateMeter(string $meterNumber): array
    {
        Log::info('[MockBackend] Validating meter', ['meter' => $meterNumber]);

        return [
            'valid' => true,
            'name' => 'MOCK CUSTOMER ' . strtoupper(substr($meterNumber, -3)),
            'address' => '123 Mock Street, Harare',
            'currency' => 'ZWG',
            'recipient_currency' => 'ZWG',
            'biller_account' => $meterNumber,
            'trace' => 'mock_trace_' . Str::random(10),
            'debit' => [
                [
                    'handler' => 'ECOCASH',
                    'type' => 'DEBIT',
                    'min' => 50,
                    'max' => 50000,
                    'label' => 'EcoCash',
                ],
            ],
        ];
    }

    public function processTransaction(array $params): array
    {
        Log::info('[MockBackend] Processing transaction', $params);

        $type = $params['type'] ?? 'zesa';
        $amount = $params['amount'] ?? 100;
        $meterNumber = $params['meter_number'] ?? '';
        $phoneNumber = $params['phone_number'] ?? '';

        $reference = 'mock_' . Str::random(12);
        $token = null;

        if ($type === 'zesa') {
            $token = $this->generateZesaToken($amount);
        }

        return [
            'success' => true,
            'transaction' => [
                'status' => 'COMPLETED',
                'uid' => $reference,
                'external_uid' => $reference,
                'customer_reference' => $reference,
                'payment_amount' => $amount,
                'biller_status' => 'COMPLETED',
                'payment_status' => 'COMPLETED',
                'reference' => $reference,
                'token' => $token,
            ],
            'confirmation' => [],
            'raw_response' => [
                'mock' => true,
                'type' => $type,
            ],
        ];
    }

    protected function generateZesaToken(int $amount): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        }
        return implode('-', $segments);
    }
}