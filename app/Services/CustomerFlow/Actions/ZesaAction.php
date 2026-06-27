<?php

namespace App\Services\CustomerFlow\Actions;

use App\Jobs\ProcessZesaTransaction;
use App\Models\Customer;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use App\Services\CustomerFlow\Support\PaymentMethodsBuilder;
use App\Services\MeterValidationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ZesaAction implements ScreenActionInterface
{
    public function __construct(
        private MeterValidationService $meterService,
        private PaymentMethodsBuilder $paymentMethodsBuilder,
    ) {}

    public function handledScreens(): array
    {
        return ['ZESA_SCREEN'];
    }

    public function init(string $screen, array $data, Customer $customer): array
    {
        $responseData = [
            'meter_valid' => false,
            'customer_name' => '',
            'customer_address' => '',
            'meter_currency' => 'ZWG',
            'ecocash_number' => $customer->ecocash_number ?? '',
            'payment_methods' => $this->paymentMethodsBuilder->build($data['meter_currency'] ?? 'ZWG'),
        ];
        Log::debug('Flow: ZESA_SCREEN init data', $responseData);
        return [
            'screen' => 'ZESA_SCREEN',
            'data' => $responseData,
        ];
    }

    public function dataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;
        $meterNumber = $data['meter_number'] ?? '';

        if ($trigger === 'verify_meter_number') {
            return $this->verifyMeter($meterNumber, $data['ecocash_number'] ?? '');
        }

        if ($trigger === 'buy_zesa') {
            return $this->purchase($customer, $data, $flowToken);
        }

        return [
            'screen' => 'ZESA_SCREEN',
            'data' => ['error_message' => 'Please enter a meter number.'],
        ];
    }

    private function verifyMeter(string $meterNumber, string $ecocashNumber = ''): array
    {
        $result = Cache::remember("validation/$meterNumber", 360, function () use ($meterNumber) {
            return $this->meterService->validate($meterNumber);
        });

        $currency = $result['currency'] ?? 'ZWG';

        return [
            'screen' => 'ZESA_SCREEN',
            'data' => [
                'meter_valid' => $result['valid'] ?? false,
                'customer_name' => $result['name'] ?? '',
                'customer_address' => $result['address'] ?? '',
                'meter_currency' => $currency,
                'ecocash_number' => $ecocashNumber,
                'payment_methods' => $this->paymentMethodsBuilder->build($currency),
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid meter number.',
            ],
        ];
    }

    private function purchase(Customer $customer, array $data, string $flowToken): array
    {
        $meterNumber = $data['meter_number'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $paymentMethod = $data['payment'] ?? 'ecocash';
        $email = $data['email'] ?? '';
        $ecocashNumber = $data['ecocash_number'] ?? '';
        $recipientPhone = $data['recipient_phone'] ?? null;

        $currency = match ($paymentMethod) {
            'stripe' => 'USD',
            default => 'ZWG',
        };

        $params = [
            'type' => 'zesa',
            'meter_number' => $meterNumber,
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'email' => $email,
            'ecocash_number' => $ecocashNumber,
            'recipient_phone' => $recipientPhone,
            'guest_id' => "Customer {$customer->id}",
        ];

        $customerData = [
            'wa_id' => $customer->wa_id,
            'name' => $customer->name ?? 'Customer',
        ];

        ProcessZesaTransaction::dispatch($params, $customerData, $flowToken)
            ->onQueue('transactions');

        $currencyLabel = $currency === 'USD' ? 'USD' : 'ZWG';
        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'success' => true,
                        'message' => "Your ZESA purchase of {$amount} {$currencyLabel} for meter {$meterNumber} is being processed. You will receive a WhatsApp notification once complete.",
                        'reference' => 'queued',
                        'close_flow' => true,
                    ],
                ],
            ],
        ];
    }
}
