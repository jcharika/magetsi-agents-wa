<?php

namespace App\Http\Controllers\Traits\FlowDataController;

use App\Jobs\ProcessAirtimeTransaction;
use App\Jobs\ProcessBundleTransaction;
use App\Jobs\ProcessZesaTransaction;
use App\Jobs\ProcessTeloneTransaction;
use App\Jobs\ProcessBillerTransaction;
use App\Models\Customer;
use App\Services\BackendManager;
use App\Services\MeterValidationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait CustomerFlowHandler
{
    abstract protected function backend(): BackendManager;
    abstract protected function meterService(): MeterValidationService;
    abstract protected function buildSuccessResponse(string $flowToken, array $extraParams = []): array;

    private static array $screenServiceMap = [
        'ZESA_SCREEN' => 'ZESA',
        'AIRTIME_SCREEN' => 'AIRTIME',
        'BUNDLES_SCREEN' => 'BUNDLES',
        'ECONET_USD_BUNDLE_SCREEN' => 'BUNDLES',
        'ECONET_ZWG_BUNDLE_SCREEN' => 'BUNDLES',
        'ECONET_SMARTSUITE_BUNDLE_SCREEN' => 'BUNDLES',
        'NETONE_USD_BUNDLE_SCREEN' => 'BUNDLES',
        'TELONE_HOME_SCREEN' => 'TELONE',
        'TELONE_SCREEN' => 'TELONE',
        'TELONE_USD_SCREEN' => 'TELONE',
        'BILLERS_SCREEN' => 'BILLERS',
        'SUPPORT_SCREEN' => 'SUPPORT',
    ];

    private function isCustomerServiceEnabled(string $service): bool
    {
        return (bool) config("whatsapp.customer_services.{$service}", true);
    }

    private function serviceScreenDisabledResponse(string $screen): ?array
    {
        $service = self::$screenServiceMap[$screen] ?? null;
        if ($service && !$this->isCustomerServiceEnabled($service)) {
            Log::debug("Flow: service '{$service}' disabled, redirecting to HOME_SCREEN");
            return [
                'screen' => 'HOME_SCREEN',
                'data' => [
                    'error_message' => "This service is currently unavailable.",
                    'enabled_services' => $this->buildServiceNavItems(),
                ],
            ];
        }
        return null;
    }

    private function buildServiceNavItems(): array
    {
        $iconDir = public_path('images/service-icons');
        $defaultMeterCurrency = 'ZWG';

        $serviceDefs = [
            'ZESA' => [
                'screen' => 'ZESA_SCREEN',
                'title' => 'Buy Electricity (ZESA)',
                'desc' => 'Prepaid tokens',
                'file' => 'zesa.png',
                'alt' => 'ZESA electricity',
                'payload' => [
                    'trigger' => 'init_zesa',
                    'meter_valid' => false,
                    'customer_name' => '',
                    'customer_address' => '',
                    'meter_currency' => $defaultMeterCurrency,
                    'ecocash_number' => '',
                    'payment_methods' => [
                        ['id' => 'ecocash', 'title' => "EcoCash $defaultMeterCurrency"],
                        ['id' => 'stripe', 'title' => 'International Card'],
                    ],
                ],
            ],
            'AIRTIME' => [
                'screen' => 'AIRTIME_SCREEN',
                'title' => 'Buy Airtime',
                'desc' => 'NetOne and Econet',
                'file' => 'airtime.png',
                'alt' => 'Airtime',
                'payload' => [
                    'trigger' => 'init_airtime',
                ],
            ],
            'BUNDLES' => [
                'screen' => 'BUNDLES_SCREEN',
                'title' => 'Buy Bundles',
                'desc' => 'Data for all nets',
                'file' => 'bundles.png',
                'alt' => 'Data bundles',
                'payload' => [
                    'trigger' => 'init_bundles',
                    'ecocash_number' => '',
                ],
            ],
            'TELONE' => [
                'screen' => 'TELONE_HOME_SCREEN',
                'title' => 'TelOne WiFi',
                'desc' => 'Broadband bundles',
                'file' => 'telone.png',
                'alt' => 'TelOne',
                'payload' => [
                    'trigger' => 'init_telone',
                ],
            ],
            'BILLERS' => [
                'screen' => 'BILLERS_SCREEN',
                'title' => 'Pay Bills',
                'desc' => 'Third-party billers',
                'file' => 'billers.png',
                'alt' => 'Billers',
                'payload' => [
                    'trigger' => 'init_billers',
                    'ecocash_number' => '',
                ],
            ],
            'SUPPORT' => [
                'screen' => 'SUPPORT_SCREEN',
                'title' => 'Contact Support',
                'desc' => 'Get help',
                'file' => 'support.png',
                'alt' => 'Support',
                'payload' => [
                    'trigger' => 'init_support',
                ],
            ],
        ];

        $items = [];
        foreach ($serviceDefs as $envSuffix => $svc) {
            if (!$this->isCustomerServiceEnabled($envSuffix)) continue;

            $iconPath = $iconDir . '/' . $svc['file'];
            $image = '';
            if (file_exists($iconPath)) {
                $image = base64_encode(file_get_contents($iconPath));
            }

            $items[] = [
                'id' => $svc['screen'],
                'start' => [
                    'image' => $image,
                    'alt-text' => $svc['alt'],
                ],
                'main-content' => [
                    'title' => $svc['title'],
                    'description' => $svc['desc'],
                ],
                'on-click-action' => [
                    'name' => 'data_exchange',
                    'payload' => $svc['payload'],
                ],
            ];
        }

        return $items;
    }

    protected function handleCustomerInit(string $screen, array $data, Customer $customer): array
    {
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        Log::debug('Flow: handleCustomerInit', ['screen' => $screen, 'hasData' => !empty($data)]);

        if (!$screen || $screen === 'HOME_SCREEN') {
            $navItems = $this->buildServiceNavItems();
            return [
                'screen' => 'HOME_SCREEN',
                'data' => [
                    'meter_valid' => false,
                    'customer_name' => '',
                    'customer_address' => '',
                    'meter_currency' => 'ZWG',
                    'enabled_services' => $navItems,
                ],
            ];
        }

        $disabled = $this->serviceScreenDisabledResponse($screen);
        if ($disabled) return $disabled;

        if ($screen === 'ZESA_SCREEN') {
            $responseData = [
                'meter_valid' => false,
                'customer_name' => '',
                'customer_address' => '',
                'meter_currency' => 'ZWG',
                'ecocash_number' => $customer->ecocash_number ?? '',
                'payment_methods' => $this->buildPaymentMethods($data['meter_currency'] ?? 'ZWG'),
            ];
            Log::debug('Flow: ZESA_SCREEN init data', $responseData);
            return [
                'screen' => 'ZESA_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'AIRTIME_SCREEN') {
            $responseData = [
                'ecocash_number' => $customer->ecocash_number ?? '',
                'airtime_options' => $this->buildAirtimeOptions(),
            ];
            Log::debug('Flow: AIRTIME_SCREEN init data', $responseData);
            return [
                'screen' => 'AIRTIME_SCREEN',
                'data' => $responseData,
            ];
        }

        $airtimeSubScreens = ['ECONET_USD_SCREEN', 'ECONET_ZWG_SCREEN', 'NETONE_USD_SCREEN'];
        if (in_array($screen, $airtimeSubScreens)) {
            $currency = $data['currency'] ?? (str_contains($screen, 'USD') ? 'USD' : 'ZWG');
            $responseData = [
                'ecocash_number' => $customer->ecocash_number ?? '',
                'network' => $data['network'] ?? '',
                'currency' => $currency,
                'payment_methods' => $this->buildPaymentMethods($currency),
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

        if ($screen === 'BUNDLES_SCREEN') {
            $responseData = [
                'ecocash_number' => $customer->ecocash_number ?? '',
                'bundle_options' => $this->buildBundleOptions(),
            ];
            Log::debug('Flow: BUNDLES_SCREEN init data', $responseData);
            return [
                'screen' => 'BUNDLES_SCREEN',
                'data' => $responseData,
            ];
        }

        $bundleSubScreens = ['ECONET_USD_BUNDLE_SCREEN', 'ECONET_ZWG_BUNDLE_SCREEN', 'ECONET_SMARTSUITE_BUNDLE_SCREEN', 'NETONE_USD_BUNDLE_SCREEN'];
        if (in_array($screen, $bundleSubScreens)) {
            $currency = $data['currency'] ?? (str_contains($screen, 'USD') ? 'USD' : 'ZWG');
            $responseData = [
                'ecocash_number' => $customer->ecocash_number ?? '',
                'network' => $data['network'] ?? '',
                'currency' => $currency,
                'payment_methods' => $this->buildPaymentMethods($currency),
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

        if ($screen === 'TELONE_HOME_SCREEN') {
            $responseData = [
                'telone_options' => $this->buildTeloneOptions(),
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
                'payment_methods' => $this->buildPaymentMethods('ZWG'),
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
                'payment_methods' => $this->buildPaymentMethods('USD'),
            ];
            Log::debug('Flow: TELONE_USD_SCREEN init data', $responseData);
            return [
                'screen' => 'TELONE_USD_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'BILLERS_SCREEN') {
            Log::debug('Flow: BILLERS_SCREEN init data');
            return [
                'screen' => 'BILLERS_SCREEN',
                'data' => [],
            ];
        }

        if ($screen === 'SUPPORT_SCREEN') {
            Log::debug('Flow: SUPPORT_SCREEN init data');
            return [
                'screen' => 'SUPPORT_SCREEN',
                'data' => [],
            ];
        }

        Log::warning('Flow: Unknown screen in init', ['screen' => $screen]);
        return [
            'screen' => 'HOME_SCREEN',
            'data' => [],
        ];
    }

    protected function handleCustomerDataExchange(string $screen, array $data, Customer $customer, string $flowToken): array
    {
        Log::debug('Flow: handleCustomerDataExchange', ['screen' => $screen, 'data' => $data]);

        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);

        // HOME_SCREEN data_exchange — triggered by NavigationList item clicks
        if ($screen === 'HOME_SCREEN') {
            $trigger = $data['trigger'] ?? '';
            // Map item triggers to target screens
            $triggerToScreen = [
                'nav_click' => null, // no default, check item data
                'init_zesa' => 'ZESA_SCREEN',
                'init_airtime' => 'AIRTIME_SCREEN',
                'init_bundles' => 'BUNDLES_SCREEN',
                'init_telone' => 'TELONE_HOME_SCREEN',
                'init_billers' => 'BILLERS_SCREEN',
                'init_support' => 'SUPPORT_SCREEN',
            ];
            $targetScreen = $triggerToScreen[$trigger] ?? null;
            // If nav_click with no item-level trigger, try to find target from item id or other keys
            if (!$targetScreen) {
                $itemId = $data['id'] ?? '';
                $targetScreen = match ($itemId) {
                    'ZESA_SCREEN' => 'ZESA_SCREEN',
                    'AIRTIME_SCREEN' => 'AIRTIME_SCREEN',
                    'BUNDLES_SCREEN' => 'BUNDLES_SCREEN',
                    'TELONE_HOME_SCREEN' => 'TELONE_HOME_SCREEN',
                    'BILLERS_SCREEN' => 'BILLERS_SCREEN',
                    'SUPPORT_SCREEN' => 'SUPPORT_SCREEN',
                    default => null,
                };
            }
            if ($targetScreen) {
                return $this->handleCustomerInit($targetScreen, $data, $customer);
            }
        }

        $airtimeSubScreens = ['AIRTIME_SCREEN', 'ECONET_USD_SCREEN', 'ECONET_ZWG_SCREEN', 'NETONE_USD_SCREEN'];
        if (in_array($screen, $airtimeSubScreens)) {
            return $this->handleBuyAirtime($customer, $data, $flowToken, $screen);
        }

        $bundleSubScreens = ['BUNDLES_SCREEN', 'ECONET_USD_BUNDLE_SCREEN', 'ECONET_ZWG_BUNDLE_SCREEN', 'ECONET_SMARTSUITE_BUNDLE_SCREEN', 'NETONE_USD_BUNDLE_SCREEN'];
        if (in_array($screen, $bundleSubScreens)) {
            return $this->handleBuyBundle($customer, $data, $flowToken, $screen);
        }

        if ($screen === 'ZESA_SCREEN') {
            return $this->handleBuyZesaDataExchange($customer, $data, $flowToken);
        }

        if ($screen === 'TELONE_SCREEN' || $screen === 'TELONE_USD_SCREEN') {
            return $this->handleTeloneDataExchange($customer, $data, $flowToken);
        }

        if ($screen === 'BILLERS_SCREEN') {
            return $this->handleBillerDataExchange($customer, $data, $flowToken);
        }

        return $this->buildSuccessResponse($flowToken);
    }

    protected function handleBuyZesaDataExchange(Customer $customer, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;
        $meterNumber = $data['meter_number'] ?? '';

        if ($trigger === 'verify_meter_number') {
            return $this->verifyMeterNumber($meterNumber, $data['ecocash_number'] ?? '');
        }

        if ($trigger === 'buy_zesa') {
            return $this->processZesaPurchase($customer, $data, $flowToken);
        }

        return [
            'screen' => 'ZESA_SCREEN',
            'data' => ['error_message' => 'Please enter a meter number.'],
        ];
    }

    protected function verifyMeterNumber(string $meterNumber, string $ecocashNumber = ''): array
    {
        $result = Cache::remember("validation/$meterNumber", 360, function () use ($meterNumber) {
            return $this->meterService()->validate($meterNumber);
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
                'payment_methods' => $this->buildPaymentMethods($currency),
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid meter number.',
            ],
        ];
    }

    protected function processZesaPurchase(Customer $customer, array $data, string $flowToken): array
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

    protected function handleBuyAirtime(Customer $customer, array $data, string $flowToken, string $currentScreen = 'AIRTIME_SCREEN'): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger !== 'buy_airtime') {
            $fallbackScreen = match (true) {
                str_contains($currentScreen, 'ECONET_USD') => 'ECONET_USD_SCREEN',
                str_contains($currentScreen, 'ECONET_ZWG') => 'ECONET_ZWG_SCREEN',
                str_contains($currentScreen, 'NETONE_USD') => 'NETONE_USD_SCREEN',
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
                str_contains($currentScreen, 'ECONET_USD') => 'ECONET_USD_SCREEN',
                str_contains($currentScreen, 'ECONET_ZWG') => 'ECONET_ZWG_SCREEN',
                str_contains($currentScreen, 'NETONE_USD') => 'NETONE_USD_SCREEN',
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

    protected function handleBuyBundle(Customer $customer, array $data, string $flowToken, string $currentScreen = 'BUNDLES_SCREEN'): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger !== 'buy_bundle') {
            $fallbackScreen = match (true) {
                str_contains($currentScreen, 'ECONET_USD') => 'ECONET_USD_BUNDLE_SCREEN',
                str_contains($currentScreen, 'ECONET_ZWG') => 'ECONET_ZWG_BUNDLE_SCREEN',
                str_contains($currentScreen, 'NETONE_USD') => 'NETONE_USD_BUNDLE_SCREEN',
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
                str_contains($currentScreen, 'ECONET_USD') => 'ECONET_USD_BUNDLE_SCREEN',
                str_contains($currentScreen, 'ECONET_ZWG') => 'ECONET_ZWG_BUNDLE_SCREEN',
                str_contains($currentScreen, 'NETONE_USD') => 'NETONE_USD_BUNDLE_SCREEN',
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

    protected function handleTeloneDataExchange(Customer $customer, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_telone_account') {
            return $this->verifyTeloneAccount($data);
        }

        if ($trigger === 'buy_telone' || $trigger === 'buy_telone_usd') {
            return $this->processTelonePurchase($customer, $data, $flowToken);
        }

        return [
            'screen' => 'TELONE_SCREEN',
            'data' => ['error_message' => 'Invalid action.'],
        ];
    }

    protected function verifyTeloneAccount(array $data): array
    {
        $accountNumber = $data['account_number'] ?? '';
        $currency = $data['currency'] ?? 'ZWG';

        $result = Cache::remember("telone_validation/$accountNumber/$currency", 360, function () use ($accountNumber, $currency) {
            return $this->backend()->validate([
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

    protected function processTelonePurchase(Customer $customer, array $data, string $flowToken): array
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

    protected function handleBillerDataExchange(Customer $customer, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_biller_account') {
            return $this->verifyBillerAccount($data);
        }

        if ($trigger === 'pay_biller') {
            return $this->processBillerPayment($customer, $data, $flowToken);
        }

        return [
            'screen' => 'BILLERS_SCREEN',
            'data' => ['error_message' => 'Invalid action.'],
        ];
    }

    protected function verifyBillerAccount(array $data): array
    {
        $billerName = $data['biller_name'] ?? '';
        $accountNumber = $data['account_number'] ?? '';

        $result = Cache::remember("biller_validation/$billerName/$accountNumber", 360, function () use ($billerName, $accountNumber) {
            return $this->backend()->validate([
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

    protected function processBillerPayment(Customer $customer, array $data, string $flowToken): array
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

    private function buildAirtimeOptions(): array
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
                'payment_methods' => $this->buildPaymentMethods($opt['currency']),
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

    private function buildBundleOptions(): array
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
                'payment_methods' => $this->buildPaymentMethods($opt['currency']),
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

    private function buildTeloneOptions(): array
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
                'payment_methods' => $this->buildPaymentMethods($opt['currency']),
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

    private function buildPaymentMethods(string $currency): array
    {
        $methods = [
            ['id' => 'ecocash-usd', 'title' => 'EcoCash USD'],
        ];
        if ($currency !== 'USD') {
            $methods[] = ['id' => 'ecocash', 'title' => 'EcoCash ZWG'];
        }
        $methods[] = ['id' => 'stripe', 'title' => 'International Card'];
        return $methods;
    }

    protected function detectNetwork(string $phoneNumber): string
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
