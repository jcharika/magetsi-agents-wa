<?php

namespace App\Services\CustomerFlow\Actions;

use App\Jobs\ProcessAirtimeTransaction;
use App\Models\Customer;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use App\Services\CustomerFlow\Support\PaymentMethodsBuilder;
use Illuminate\Support\Facades\Log;

class AirtimeAction implements ScreenActionInterface
{
    public function __construct(
        private PaymentMethodsBuilder $paymentMethodsBuilder,
    ) {}

    public function handledScreens(): array
    {
        return ['AIRTIME_SCREEN', 'ECONET_USD_SCREEN', 'ECONET_ZWG_SCREEN', 'NETONE_USD_SCREEN'];
    }

    public function init(string $screen, array $data, Customer $customer): array
    {
        if ($screen === 'AIRTIME_SCREEN') {
            $responseData = [
                'ecocash_number' => $customer->ecocash_number ?? '',
                'airtime_options' => $this->buildOptions(),
            ];
            Log::debug('Flow: AIRTIME_SCREEN init data', $responseData);
            return [
                'screen' => 'AIRTIME_SCREEN',
                'data' => $responseData,
            ];
        }

        $currency = $data['currency'] ?? (str_contains($screen, 'USD') ? 'USD' : 'ZWG');
        $responseData = [
            'ecocash_number' => $customer->ecocash_number ?? '',
            'network' => $data['network'] ?? '',
            'currency' => $currency,
            'payment_methods' => $this->paymentMethodsBuilder->build($currency),
        ];
        if (str_contains($screen, 'USD')) {
            $responseData['email'] = $data['email'] ?? '';
        }
        if ($screen === 'NETONE_USD_SCREEN') {
            $responseData['categories'] = [['id' => 'AIRTIME', 'title' => 'Airtime']];
            $responseData['amount_options'] = $this->buildAmountOptions();
        }
        Log::debug("Flow: $screen init data", $responseData);
        return [
            'screen' => $screen,
            'data' => $responseData,
        ];
    }

    public function dataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger !== 'buy_airtime') {
            $fallbackScreen = match (true) {
                str_contains($screen, 'ECONET_USD') => 'ECONET_USD_SCREEN',
                str_contains($screen, 'ECONET_ZWG') => 'ECONET_ZWG_SCREEN',
                str_contains($screen, 'NETONE_USD') => 'NETONE_USD_SCREEN',
                default => 'AIRTIME_SCREEN',
            };
            return [
                'screen' => $fallbackScreen,
                'data' => ['error_message' => 'Invalid action.'],
            ];
        }

        $receiver = $data['receiver'] ?? '';
        $paymentMethod = $data['payment'] ?? 'ecocash';
        $phone = $data['phone'] ?? $data['phone_usd'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $email = $data['email'] ?? '';

        $currency = $data['currency'] ?? match ($paymentMethod) {
            'ecocash-usd', 'stripe' => 'USD',
            default => 'ZWG',
        };

        if (!$receiver || !$amount) {
            $errorScreen = match (true) {
                str_contains($screen, 'ECONET_USD') => 'ECONET_USD_SCREEN',
                str_contains($screen, 'ECONET_ZWG') => 'ECONET_ZWG_SCREEN',
                str_contains($screen, 'NETONE_USD') => 'NETONE_USD_SCREEN',
                default => 'AIRTIME_SCREEN',
            };
            return [
                'screen' => $errorScreen,
                'data' => ['error_message' => 'Please fill in all required fields.'],
            ];
        }

        $network = $data['network'] ?? $this->detectNetwork($receiver);

        $params = [
            'type' => 'airtime',
            'handler' => 'AIRTIME',
            'receiver' => $receiver,
            'payment_method' => $paymentMethod,
            'phone' => $phone,
            'amount' => $amount,
            'email' => $email,
            'currency' => $currency,
            'network' => $network,
            'ecocash_number' => $phone ?: '',
            'guest_id' => "Customer {$customer->id}",
        ];

        $customerData = [
            'wa_id' => $customer->wa_id,
            'name' => $customer->name ?? 'Customer',
        ];

        ProcessAirtimeTransaction::dispatch($params, $customerData, $flowToken)
            ->onQueue('transactions');

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'success' => true,
                        'message' => "Your {$network} airtime purchase of {$amount} ZWG for {$phone} is being processed. You will receive a WhatsApp notification once complete.",
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
            'ECONET_USD_SCREEN' => [
                'screen' => 'ECONET_USD_SCREEN',
                'title' => 'Econet USD Airtime',
                'desc' => 'US Dollar',
                'file' => 'econet_usd.png',
                'alt' => 'Econet USD',
                'network' => 'econet',
                'currency' => 'USD',
            ],
            'ECONET_ZWG_SCREEN' => [
                'screen' => 'ECONET_ZWG_SCREEN',
                'title' => 'Econet ZWG Airtime',
                'desc' => 'Zimbabwe Gold',
                'file' => 'econet_zwg.png',
                'alt' => 'Econet ZWG',
                'network' => 'econet',
                'currency' => 'ZWG',
            ],
            'NETONE_USD_SCREEN' => [
                'screen' => 'NETONE_USD_SCREEN',
                'title' => 'NetOne USD Airtime',
                'desc' => 'US Dollar',
                'file' => 'netone_usd.png',
                'alt' => 'NetOne USD',
                'network' => 'netone',
                'currency' => 'USD',
            ],
        ];

        $items = [];
        foreach ($options as $key => $opt) {
            $iconPath = $iconDir . '/' . $opt['file'];
            $image = '';
            if (file_exists($iconPath)) {
                $image = base64_encode(file_get_contents($iconPath));
            }

            $payload = [
                'ecocash_number' => '${data.ecocash_number}',
                'network' => $opt['network'],
                'currency' => $opt['currency'],
                'payment_methods' => $this->paymentMethodsBuilder->build($opt['currency']),
            ];
            if ($opt['currency'] === 'USD') {
                $payload['email'] = '';
            }
            if ($key === 'NETONE_USD_SCREEN') {
                $payload['categories'] = [['id' => 'AIRTIME', 'title' => 'Airtime']];
                $payload['amount_options'] = $this->buildAmountOptions();
            }

            $items[] = [
                'id' => $opt['screen'],
                'start' => [
                    'image' => $image,
                    'alt-text' => $opt['alt'],
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

    private function buildAmountOptions(): array
    {
        $options = [];
        for ($i = 1; $i <= 9; $i++) {
            $val = $i / 10;
            $options[] = ['id' => (string)$val, 'title' => '$' . $val];
        }
        foreach ([1, 2, 5, 10, 20] as $val) {
            $options[] = ['id' => (string)$val, 'title' => '$' . $val];
        }
        return $options;
    }

    private function detectNetwork(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber);

        if (str_starts_with($digits, '26371')) {
            return 'netone';
        }

        if (str_starts_with($digits, '26377') || str_starts_with($digits, '26378') || str_starts_with($digits, '26379')) {
            return 'econet';
        }

        return 'econet';
    }
}
