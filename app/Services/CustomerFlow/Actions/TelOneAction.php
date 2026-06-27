<?php

namespace App\Services\CustomerFlow\Actions;

use App\Jobs\ProcessTeloneTransaction;
use App\Models\Customer;
use App\Services\BackendManager;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use App\Services\CustomerFlow\Support\PaymentMethodsBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelOneAction implements ScreenActionInterface
{
    public function __construct(
        private BackendManager $backend,
        private PaymentMethodsBuilder $paymentMethodsBuilder,
    ) {}

    public function handledScreens(): array
    {
        return ['TELONE_HOME_SCREEN', 'TELONE_SCREEN', 'TELONE_USD_SCREEN'];
    }

    public function init(string $screen, array $data, Customer $customer): array
    {
        if ($screen === 'TELONE_HOME_SCREEN') {
            $responseData = [
                'telone_options' => $this->buildOptions(),
            ];
            Log::debug('Flow: TELONE_HOME_SCREEN init data', $responseData);
            return [
                'screen' => 'TELONE_HOME_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'TELONE_SCREEN') {
            $responseData = [
                'currency' => 'ZWG',
                'payment_methods' => $this->paymentMethodsBuilder->build('ZWG'),
            ];
            Log::debug('Flow: TELONE_SCREEN init data', $responseData);
            return [
                'screen' => 'TELONE_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'TELONE_USD_SCREEN') {
            $responseData = [
                'currency' => 'USD',
                'payment_methods' => $this->paymentMethodsBuilder->build('USD'),
            ];
            Log::debug('Flow: TELONE_USD_SCREEN init data', $responseData);
            return [
                'screen' => 'TELONE_USD_SCREEN',
                'data' => $responseData,
            ];
        }

        return [
            'screen' => $screen,
            'data' => [],
        ];
    }

    public function dataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_telone_account') {
            return $this->verifyAccount($data);
        }

        if ($trigger === 'buy_telone' || $trigger === 'buy_telone_usd') {
            return $this->purchase($customer, $data, $flowToken);
        }

        return [
            'screen' => 'TELONE_SCREEN',
            'data' => ['error_message' => 'Invalid action.'],
        ];
    }

    private function verifyAccount(array $data): array
    {
        $accountNumber = $data['account_number'] ?? '';
        $currency = $data['currency'] ?? 'ZWG';

        $result = Cache::remember("telone_validation/$accountNumber/$currency", 360, function () use ($accountNumber, $currency) {
            return $this->backend->validate([
                'handler' => $currency === 'USD' ? 'TELONE_USD' : 'TELONE',
                'biller_account' => $accountNumber,
            ]);
        });

        return [
            'screen' => $currency === 'USD' ? 'TELONE_USD_SCREEN' : 'TELONE_SCREEN',
            'data' => [
                'account_valid' => $result['valid'] ?? false,
                'customer_name' => $result['name'] ?? '',
                'customer_address' => $result['address'] ?? '',
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid TelOne account number.',
            ],
        ];
    }

    private function purchase(Customer $customer, array $data, string $flowToken): array
    {
        $accountNumber = $data['biller_account'] ?? '';
        $phoneNumber = $data['phone_number'] ?? '';
        $package = $data['package'] ?? '';
        $currency = $data['currency'] ?? 'ZWG';
        $ecocashNumber = $data['payment_account'] ?? '';

        if (!$accountNumber || !$phoneNumber || !$package) {
            $screen = $currency === 'USD' ? 'TELONE_USD_SCREEN' : 'TELONE_SCREEN';
            return [
                'screen' => $screen,
                'data' => ['error_message' => 'Please fill in all required fields.'],
            ];
        }

        $params = [
            'type' => 'telone',
            'handler' => $currency === 'USD' ? 'TELONE_USD' : 'TELONE',
            'biller_account' => $accountNumber,
            'phone_number' => $phoneNumber,
            'package' => $package,
            'currency' => $currency,
            'ecocash_number' => $ecocashNumber,
            'guest_id' => "Customer {$customer->id}",
        ];

        $customerData = [
            'wa_id' => $customer->wa_id,
            'name' => $customer->name ?? 'Customer',
        ];

        ProcessTeloneTransaction::dispatch($params, $customerData, $flowToken)
            ->onQueue('transactions');

        $currencyLabel = $currency === 'USD' ? 'USD' : 'ZWG';
        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'success' => true,
                        'message' => "Your TelOne WiFi purchase of {$package} for account {$accountNumber} is being processed. You will receive a WhatsApp notification once complete.",
                        'close_flow' => true,
                    ],
                ],
            ],
        ];
    }

    private function buildOptions(): array
    {
        $iconDir = public_path('images/service-icons');

        $options = [
            'TELONE_RECHARGE_USD' => [
                'title' => 'TelOne Recharge USD',
                'desc' => 'US Dollar',
                'screen' => 'TELONE_USD_SCREEN',
                'currency' => 'USD',
            ],
            'TELONE_RECHARGE_ZWG' => [
                'title' => 'TelOne Recharge ZWG',
                'desc' => 'Zimbabwe Gold',
                'screen' => 'TELONE_SCREEN',
                'currency' => 'ZWG',
            ],
            'TELONE_PURCHASE_BUNDLE_USD' => [
                'title' => 'TelOne Purchase Bundle USD',
                'desc' => 'Purchase Bundle',
                'screen' => 'TELONE_USD_SCREEN',
                'currency' => 'USD',
            ],
            'TELONE_PURCHASE_BUNDLE_ZWG' => [
                'title' => 'TelOne Purchase Bundle ZWG',
                'desc' => 'Purchase Bundle',
                'screen' => 'TELONE_SCREEN',
                'currency' => 'ZWG',
            ],
            'TELONE_BILL_PAYMENT' => [
                'title' => 'TelOne Bill Payment',
                'desc' => 'Pay your bill',
                'screen' => 'TELONE_USD_SCREEN',
                'currency' => 'USD',
            ],
        ];

        $items = [];
        foreach ($options as $itemId => $opt) {
            $iconPath = $iconDir . '/telone.png';
            $image = '';
            if (file_exists($iconPath)) {
                $image = base64_encode(file_get_contents($iconPath));
            }

            $payload = [
                'currency' => $opt['currency'],
                'ecocash_number' => '${data.ecocash_number}',
                'payment_methods' => $this->paymentMethodsBuilder->build($opt['currency']),
            ];

            $items[] = [
                'id' => $itemId,
                'start' => [
                    'image' => $image,
                    'alt-text' => $opt['title'],
                ],
                'main-content' => [
                    'title' => $opt['title'],
                    'description' => $opt['desc'],
                ],
                'on-click-action' => [
                    'name' => 'navigate',
                    'next' => ['type' => 'screen', 'name' => $opt['screen']],
                    'payload' => $payload,
                ],
            ];
        }

        return $items;
    }
}
