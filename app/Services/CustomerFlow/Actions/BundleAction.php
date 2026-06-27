<?php

namespace App\Services\CustomerFlow\Actions;

use App\Jobs\ProcessBundleTransaction;
use App\Models\Customer;
use App\Services\CustomerFlow\Contracts\ScreenActionInterface;
use App\Services\CustomerFlow\Support\PaymentMethodsBuilder;
use Illuminate\Support\Facades\Log;

class BundleAction implements ScreenActionInterface
{
    public function __construct(
        private PaymentMethodsBuilder $paymentMethodsBuilder,
    ) {}

    public function handledScreens(): array
    {
        return ['BUNDLES_SCREEN', 'ECONET_USD_BUNDLE_SCREEN', 'ECONET_ZWG_BUNDLE_SCREEN', 'ECONET_SMARTSUITE_BUNDLE_SCREEN', 'NETONE_USD_BUNDLE_SCREEN'];
    }

    public function init(string $screen, array $data, Customer $customer): array
    {
        if ($screen === 'BUNDLES_SCREEN') {
            $responseData = [
                'ecocash_number' => $customer->ecocash_number ?? '',
                'bundle_options' => $this->buildOptions(),
            ];
            Log::debug('Flow: BUNDLES_SCREEN init data', $responseData);
            return [
                'screen' => 'BUNDLES_SCREEN',
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
        if ($screen === 'NETONE_USD_BUNDLE_SCREEN') {
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

        if ($trigger !== 'buy_bundle') {
            $fallbackScreen = match (true) {
                str_contains($screen, 'ECONET_USD') => 'ECONET_USD_BUNDLE_SCREEN',
                str_contains($screen, 'ECONET_ZWG') => 'ECONET_ZWG_BUNDLE_SCREEN',
                str_contains($screen, 'NETONE_USD') => 'NETONE_USD_BUNDLE_SCREEN',
                default => 'BUNDLES_SCREEN',
            };
            return [
                'screen' => $fallbackScreen,
                'data' => ['error_message' => 'Invalid action.'],
            ];
        }

        $receiver = $data['receiver'] ?? '';
        $phoneNumber = $data['phone_number'] ?? $receiver;
        $bundleSize = $data['bundle_size'] ?? '';
        $paymentMethod = $data['payment'] ?? 'ecocash';
        $phone = $data['phone'] ?? '';
        $email = $data['email'] ?? '';

        $network = $data['network'] ?? '';
        $currency = $data['currency'] ?? match ($paymentMethod) {
            'stripe' => 'USD',
            default => 'ZWG',
        };

        if (!$network || !$phoneNumber || !$bundleSize) {
            $errorScreen = match (true) {
                str_contains($screen, 'ECONET_USD') => 'ECONET_USD_BUNDLE_SCREEN',
                str_contains($screen, 'ECONET_ZWG') => 'ECONET_ZWG_BUNDLE_SCREEN',
                str_contains($screen, 'NETONE_USD') => 'NETONE_USD_BUNDLE_SCREEN',
                default => 'BUNDLES_SCREEN',
            };
            return [
                'screen' => $errorScreen,
                'data' => ['error_message' => 'Please fill in all required fields.'],
            ];
        }

        $params = [
            'type' => 'bundle',
            'network' => $network,
            'phone_number' => $phoneNumber,
            'bundle_size' => $bundleSize,
            'payment_method' => $paymentMethod,
            'email' => $email,
            'currency' => $currency,
            'ecocash_number' => $phone ?: '',
            'guest_id' => "Customer {$customer->id}",
        ];

        $customerData = [
            'wa_id' => $customer->wa_id,
            'name' => $customer->name ?? 'Customer',
        ];

        ProcessBundleTransaction::dispatch($params, $customerData, $flowToken)
            ->onQueue('transactions');

        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => [
                        'flow_token' => $flowToken,
                        'success' => true,
                        'message' => "Your {$network} bundle purchase of {$bundleSize} for {$phoneNumber} is being processed. You will receive a WhatsApp notification once complete.",
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
            'ECONET_USD_BUNDLE_SCREEN' => [
                'title' => 'Econet USD Bundles',
                'desc' => 'US Dollar',
                'file' => 'econet_usd_bundle.png',
                'alt' => 'Econet USD',
                'network' => 'econet',
                'currency' => 'USD',
            ],
            'ECONET_ZWG_BUNDLE_SCREEN' => [
                'title' => 'Econet ZWG Bundles',
                'desc' => 'Zimbabwe Gold',
                'file' => 'econet_zwg_bundle.png',
                'alt' => 'Econet ZWG',
                'network' => 'econet',
                'currency' => 'ZWG',
            ],
            'ECONET_SMARTSUITE_BUNDLE_SCREEN' => [
                'title' => 'Econet SmartSuite Bundles',
                'desc' => 'SmartSuite',
                'file' => 'smartsuite.png',
                'alt' => 'SmartSuite',
                'network' => 'econet',
                'currency' => 'ZWG',
            ],
            'NETONE_USD_BUNDLE_SCREEN' => [
                'title' => 'NetOne USD Bundles',
                'desc' => 'US Dollar',
                'file' => 'netone_usd_bundle.png',
                'alt' => 'NetOne USD',
                'network' => 'netone',
                'currency' => 'USD',
            ],
            'TELONE_USD_SCREEN_TELONE_USD' => [
                'title' => 'TelOne USD Bundles',
                'desc' => 'US Dollar',
                'file' => 'telone.png',
                'alt' => 'TelOne USD',
                'screen' => 'TELONE_USD_SCREEN',
                'currency' => 'USD',
                'skipNetwork' => true,
            ],
            'TELONE_USD_SCREEN_PURCHASE' => [
                'title' => 'TelOne Purchase Bundle USD',
                'desc' => 'Purchase Bundle',
                'file' => 'telone.png',
                'alt' => 'TelOne USD',
                'screen' => 'TELONE_USD_SCREEN',
                'currency' => 'USD',
                'skipNetwork' => true,
            ],
            'TELONE_SCREEN' => [
                'title' => 'TelOne ZWG Bundles',
                'desc' => 'Zimbabwe Gold',
                'file' => 'telone.png',
                'alt' => 'TelOne ZWG',
                'screen' => 'TELONE_SCREEN',
                'currency' => 'ZWG',
                'skipNetwork' => true,
            ],
        ];

        $items = [];
        foreach ($options as $key => $opt) {
            $iconPath = $iconDir . '/' . $opt['file'];
            $image = '';
            if (file_exists($iconPath)) {
                $image = base64_encode(file_get_contents($iconPath));
            }

            $screenId = $opt['screen'] ?? $key;
            $itemId = $key;

            $payload = [
                'ecocash_number' => '${data.ecocash_number}',
                'currency' => $opt['currency'],
                'payment_methods' => $this->paymentMethodsBuilder->build($opt['currency']),
            ];
            if (empty($opt['skipNetwork'])) {
                $payload['network'] = $opt['network'];
            }
            if ($opt['currency'] === 'USD') {
                $payload['email'] = '';
            }
            if ($key === 'NETONE_USD_BUNDLE_SCREEN') {
                $payload['categories'] = [['id' => 'AIRTIME', 'title' => 'Airtime']];
                $payload['amount_options'] = $this->buildAmountOptions();
            }

            $items[] = [
                'id' => $itemId,
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
                    'next' => ['type' => 'screen', 'name' => $screenId],
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
}
