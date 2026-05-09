<?php

namespace App\Http\Controllers\Traits\FlowDataController;

use App\Jobs\ProcessAirtimeTransaction;
use App\Jobs\ProcessBundleTransaction;
use App\Jobs\ProcessZesaTransaction;
use App\Jobs\ProcessTeloneTransaction;
use App\Jobs\ProcessBillerTransaction;
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
        // Normalize screen names
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        Log::info('Flow: handleCustomerInit', ['screen' => $screen, 'hasData' => !empty($data)]);

        if (!$screen || $screen === 'HOME_SCREEN') {
            return [
                'screen' => 'HOME_SCREEN',
                'data' => [
                    'ecocash_number' => $agent->ecocash_number ?? '',
                    'meter_valid' => false,
                    'customer_name' => '',
                    'customer_address' => '',
                    'meter_currency' => 'ZWG',
                ],
            ];
        }

        if ($screen === 'ZESA_SCREEN') {
            $responseData = [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'meter_valid' => false,
                'customer_name' => '',
                'customer_address' => '',
                'meter_currency' => 'ZWG',
            ];
            Log::info('Flow: ZESA_SCREEN init data', $responseData);
            return [
                'screen' => 'ZESA_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'AIRTIME_SCREEN') {
            $responseData = [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'networks' => [
                    ['id' => 'econet', 'title' => 'Econet'],
                    ['id' => 'netone', 'title' => 'NetOne'],
                ],
                'payment_methods' => [
                    ['id' => 'ecocash', 'title' => 'EcoCash ZWG'],
                    ['id' => 'ecocash-usd', 'title' => 'EcoCash USD'],
                    ['id' => 'stripe', 'title' => 'International Card'],
                ],
            ];
            Log::info('Flow: AIRTIME_SCREEN init data', $responseData);
            return [
                'screen' => 'AIRTIME_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'BUNDLES_SCREEN') {
            $responseData = [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'networks' => [
                    ['id' => 'econet', 'title' => 'Econet'],
                    ['id' => 'netone', 'title' => 'NetOne'],
                    ['id' => 'smartsuite', 'title' => 'SmartSuite'],
                ],
            ];
            Log::info('Flow: BUNDLES_SCREEN init data', $responseData);
            return [
                'screen' => 'BUNDLES_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'TELONE_HOME_SCREEN') {
            Log::info('Flow: TELONE_HOME_SCREEN init data');
            return [
                'screen' => 'TELONE_HOME_SCREEN',
                'data' => [],
            ];
        }

        if ($screen === 'TELONE_SCREEN') {
            $responseData = [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'currency' => 'ZWG',
            ];
            Log::info('Flow: TELONE_SCREEN init data', $responseData);
            return [
                'screen' => 'TELONE_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'TELONE_USD_SCREEN') {
            $responseData = [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'currency' => 'USD',
            ];
            Log::info('Flow: TELONE_USD_SCREEN init data', $responseData);
            return [
                'screen' => 'TELONE_USD_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'BILLERS_SCREEN') {
            $responseData = [
                'ecocash_number' => $agent->ecocash_number ?? '',
            ];
            Log::info('Flow: BILLERS_SCREEN init data', $responseData);
            return [
                'screen' => 'BILLERS_SCREEN',
                'data' => $responseData,
            ];
        }

        if ($screen === 'SUPPORT_SCREEN') {
            Log::info('Flow: SUPPORT_SCREEN init data');
            return [
                'screen' => 'SUPPORT_SCREEN',
                'data' => [],
            ];
        }

        Log::warning('Flow: Unknown screen in init', ['screen' => $screen]);
        return [
            'screen' => 'HOME_SCREEN',
            'data' => [
                'ecocash_number' => $agent->ecocash_number ?? '',
            ],
        ];
    }

    protected function handleCustomerDataExchange(string $screen, array $data, Agent $agent, string $flowToken): array
    {
        Log::info('Flow: handleCustomerDataExchange', ['screen' => $screen, 'data' => $data]);

        // Normalize screen names
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);

        if ($screen === 'AIRTIME_SCREEN') {
            return $this->handleBuyAirtime($agent, $data, $flowToken);
        }

        if ($screen === 'BUNDLES_SCREEN') {
            return $this->handleBuyBundle($agent, $data, $flowToken);
        }

        if ($screen === 'ZESA_SCREEN') {
            return $this->handleBuyZesaDataExchange($agent, $data, $flowToken);
        }

        if ($screen === 'TELONE_SCREEN' || $screen === 'TELONE_USD_SCREEN') {
            return $this->handleTeloneDataExchange($agent, $data, $flowToken);
        }

        if ($screen === 'BILLERS_SCREEN') {
            return $this->handleBillerDataExchange($agent, $data, $flowToken);
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
                        'close_flow' => true,
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

        $receiver = $data['receiver'] ?? '';
        $paymentMethod = $data['payment'] ?? 'ecocash';
        $phone = $data['phone'] ?? $data['phone_usd'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $email = $data['email'] ?? '';

        $currency = match ($paymentMethod) {
            'ecocash-usd', 'stripe' => 'USD',
            default => 'ZWG',
        };

        if (!$receiver || !$amount) {
            return [
                'screen' => 'AIRTIME_SCREEN',
                'data' => ['error_message' => 'Please fill in all required fields.'],
            ];
        }

        $network = $this->detectNetwork($receiver);

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
            'ecocash_number' => $phone ?: $agent->ecocash_number,
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
                        'close_flow' => true,
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
                        'close_flow' => true,
                    ],
                ],
            ],
        ];
    }

    protected function handleTeloneDataExchange(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_telone_account') {
            return $this->verifyTeloneAccount($data);
        }

        if ($trigger === 'buy_telone' || $trigger === 'buy_telone_usd') {
            return $this->processTelonePurchase($agent, $data, $flowToken);
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

    protected function processTelonePurchase(Agent $agent, array $data, string $flowToken): array
    {
        $accountNumber = $data['biller_account'] ?? '';
        $phoneNumber = $data['phone_number'] ?? '';
        $package = $data['package'] ?? '';
        $currency = $data['currency'] ?? 'ZWG';
        $ecocashNumber = $data['payment_account'] ?? $agent->ecocash_number;

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
            'guest_id' => "Agent {$agent->id}",
        ];

        $agentData = [
            'wa_id' => $agent->wa_id,
            'name' => $agent->name,
            'ecocash_number' => $agent->ecocash_number,
        ];

        ProcessTeloneTransaction::dispatch($params, $agentData, $flowToken)
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

    protected function handleBillerDataExchange(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_biller_account') {
            return $this->verifyBillerAccount($data);
        }

        if ($trigger === 'pay_biller') {
            return $this->processBillerPayment($agent, $data, $flowToken);
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

    protected function processBillerPayment(Agent $agent, array $data, string $flowToken): array
    {
        $billerName = $data['biller_name'] ?? '';
        $accountNumber = $data['biller_account'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $currency = $data['currency'] ?? 'ZWG';
        $ecocashNumber = $data['payment_account'] ?? $agent->ecocash_number;

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
            'guest_id' => "Agent {$agent->id}",
        ];

        $agentData = [
            'wa_id' => $agent->wa_id,
            'name' => $agent->name,
            'ecocash_number' => $agent->ecocash_number,
        ];

        ProcessBillerTransaction::dispatch($params, $agentData, $flowToken)
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
