<?php

namespace App\Services\CustomerFlow\Actions;

use App\Jobs\ProcessBillerTransaction;
use App\Models\Customer;
use App\Services\BackendManager;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BillerAction implements ScreenActionInterface
{
    public function __construct(
        private BackendManager $backend,
    ) {}

    public function handledScreens(): array
    {
        return ['BILLERS_SCREEN'];
    }

    public function init(string $screen, array $data, Customer $customer): array
    {
        Log::debug('Flow: BILLERS_SCREEN init data');
        return [
            'screen' => 'BILLERS_SCREEN',
            'data' => [],
        ];
    }

    public function dataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_biller_account') {
            return $this->verifyAccount($data);
        }

        if ($trigger === 'pay_biller') {
            return $this->pay($customer, $data, $flowToken);
        }

        return [
            'screen' => 'BILLERS_SCREEN',
            'data' => ['error_message' => 'Invalid action.'],
        ];
    }

    private function verifyAccount(array $data): array
    {
        $billerName = $data['biller_name'] ?? '';
        $accountNumber = $data['account_number'] ?? '';

        $result = Cache::remember("biller_validation/$billerName/$accountNumber", 360, function () use ($billerName, $accountNumber) {
            return $this->backend->validate([
                'handler' => 'BILLERS',
                'biller_name' => $billerName,
                'biller_account' => $accountNumber,
            ]);
        });

        return [
            'screen' => 'BILLERS_SCREEN',
            'data' => [
                'account_valid' => $result['valid'] ?? false,
                'customer_name' => $result['name'] ?? '',
                'customer_address' => $result['address'] ?? '',
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid biller account.',
            ],
        ];
    }

    private function pay(Customer $customer, array $data, string $flowToken): array
    {
        $billerName = $data['biller_name'] ?? '';
        $accountNumber = $data['biller_account'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $currency = $data['currency'] ?? 'ZWG';
        $ecocashNumber = $data['payment_account'] ?? '';

        if (!$billerName || !$accountNumber || !$amount) {
            return [
                'screen' => 'BILLERS_SCREEN',
                'data' => ['error_message' => 'Please fill in all required fields.'],
            ];
        }

        $params = [
            'type' => 'biller',
            'handler' => 'BILLERS',
            'biller_name' => $billerName,
            'biller_account' => $accountNumber,
            'amount' => $amount,
            'currency' => $currency,
            'ecocash_number' => $ecocashNumber,
            'guest_id' => "Customer {$customer->id}",
        ];

        $customerData = [
            'wa_id' => $customer->wa_id,
            'name' => $customer->name ?? 'Customer',
        ];

        ProcessBillerTransaction::dispatch($params, $customerData, $flowToken)
            ->onQueue('transactions');

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'success' => true,
                        'message' => "Your payment of {$amount} {$currency} to {$billerName} (Account: {$accountNumber}) is being processed. You will receive a WhatsApp notification once complete.",
                        'close_flow' => true,
                    ],
                ],
            ],
        ];
    }
}
