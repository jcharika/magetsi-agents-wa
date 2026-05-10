<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Magetsi\Common\Models\Agent;
use Magetsi\Common\Services\BackendManager;
use Magetsi\Common\Services\FlowEncryptionService;
use Magetsi\Common\Services\MeterValidationService;
use Magetsi\Common\Traits\FlowDataControllerShared;
use App\Jobs\ProcessAirtimeTransaction;
use App\Jobs\ProcessBillerTransaction;
use App\Jobs\ProcessBundleTransaction;
use App\Jobs\ProcessTeloneTransaction;
use App\Jobs\ProcessZesaTransaction;

class CustomerFlowController extends Controller
{
    use FlowDataControllerShared;

    public function __construct(
        protected FlowEncryptionService  $encryption,
        protected MeterValidationService $meterService,
        protected BackendManager         $backend,
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $rawBody = $request->getContent();

            $signature = $request->header('X-Hub-Signature-256', '');
            if ($signature && !$this->encryption->verifySignature($rawBody, $signature)) {
                Log::warning('CustomerFlow: signature verification failed.');
                return response('Invalid signature', 432);
            }

            $body = $request->all();

            $encryptedFlowData = $body['encrypted_flow_data'] ?? null;
            $encryptedAesKey = $body['encrypted_aes_key'] ?? null;
            $initialVector = $body['initial_vector'] ?? null;

            if (!$encryptedFlowData || !$encryptedAesKey || !$initialVector) {
                Log::error('CustomerFlow: missing encrypted fields.');
                return response('Missing encryption fields', 400);
            }

            $result = $this->encryption->decryptRequest(
                $encryptedFlowData,
                $encryptedAesKey,
                $initialVector
            );

            $decryptedData = $result['decrypted_data'];
            $aesKey = $result['aes_key'];
            $iv = $result['iv'];

            $action = $decryptedData['action'] ?? '';
            $screen = $decryptedData['screen'] ?? '';
            $data = $decryptedData['data'] ?? [];
            $flowToken = $decryptedData['flow_token'] ?? '';

            Log::info('CustomerFlow: request', ['action' => $action, 'screen' => $screen, 'flowToken' => $flowToken]);

            $responsePayload = match ($action) {
                'ping' => $this->handlePing(),
                'INIT' => $this->handleInit($screen, $data, $flowToken),
                'navigate' => $this->handleNavigate($screen, $flowToken),
                'data_exchange' => $this->handleDataExchange($screen, $data, $flowToken),
                'BACK' => $this->handleBack($screen),
                default => $this->handleErrorNotification($action, $data),
            };

            if (!is_array($responsePayload)) {
                throw new \RuntimeException('Invalid response payload');
            }

            $encryptedResponse = $this->encryption->encryptResponse($responsePayload, $aesKey, $iv);

            return response($encryptedResponse, 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Throwable $exception) {
            Log::error('CustomerFlow: error', ['error' => $exception->getMessage()]);
            return response('Internal server error', 204);
        }
    }

    protected function handlePing(): array
    {
        return ['data' => ['status' => 'active']];
    }

    protected function handleInit(string $screen, array $data, string $flowToken): array
    {
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        $agent = $this->resolveCustomerAgent($flowToken);

        if (!$screen || $screen === 'HOME_SCREEN') {
            return $this->screenResponse('HOME_SCREEN', [
                'ecocash_number' => $agent->ecocash_number ?? '',
            ]);
        }

        if ($screen === 'ZESA_SCREEN') {
            return $this->screenResponse('ZESA_SCREEN', [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'meter_valid' => false,
                'customer_name' => '',
                'customer_address' => '',
                'meter_currency' => 'ZWG',
            ]);
        }

        if ($screen === 'AIRTIME_SCREEN') {
            return $this->screenResponse('AIRTIME_SCREEN', [
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
            ]);
        }

        if ($screen === 'BUNDLES_SCREEN') {
            return $this->screenResponse('BUNDLES_SCREEN', [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'networks' => [
                    ['id' => 'econet', 'title' => 'Econet'],
                    ['id' => 'netone', 'title' => 'NetOne'],
                    ['id' => 'smartsuite', 'title' => 'SmartSuite'],
                ],
            ]);
        }

        if ($screen === 'TELONE_HOME_SCREEN') {
            return $this->screenResponse('TELONE_HOME_SCREEN', []);
        }

        if ($screen === 'TELONE_SCREEN') {
            return $this->screenResponse('TELONE_SCREEN', [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'currency' => 'ZWG',
            ]);
        }

        if ($screen === 'TELONE_USD_SCREEN') {
            return $this->screenResponse('TELONE_USD_SCREEN', [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'currency' => 'USD',
            ]);
        }

        if ($screen === 'BILLERS_SCREEN') {
            return $this->screenResponse('BILLERS_SCREEN', [
                'ecocash_number' => $agent->ecocash_number ?? '',
            ]);
        }

        if ($screen === 'SUPPORT_SCREEN') {
            return $this->screenResponse('SUPPORT_SCREEN', []);
        }

        return $this->screenResponse('HOME_SCREEN', [
            'ecocash_number' => $agent->ecocash_number ?? '',
        ]);
    }

    protected function handleNavigate(string $screen, string $flowToken): array
    {
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        $agent = $this->resolveCustomerAgent($flowToken);
        return $this->handleInit($screen, [], $flowToken);
    }

    protected function handleDataExchange(string $screen, array $data, string $flowToken): array
    {
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        $agent = $this->resolveCustomerAgent($flowToken);

        return match ($screen) {
            'AIRTIME_SCREEN' => $this->handleBuyAirtime($agent, $data, $flowToken),
            'BUNDLES_SCREEN' => $this->handleBuyBundle($agent, $data, $flowToken),
            'ZESA_SCREEN' => $this->handleZesaDataExchange($agent, $data, $flowToken),
            'TELONE_SCREEN', 'TELONE_USD_SCREEN' => $this->handleTeloneDataExchange($agent, $data, $flowToken),
            'BILLERS_SCREEN' => $this->handleBillerDataExchange($agent, $data, $flowToken),
            default => $this->buildSuccessResponse($flowToken),
        };
    }

    protected function handleBack(string $screen): array
    {
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        return [
            'screen' => 'HOME_SCREEN',
            'data' => [],
        ];
    }

    protected function handleErrorNotification(string $action, array $data): array
    {
        Log::warning('CustomerFlow: error notification', [
            'action' => $action,
            'error' => $data['error'] ?? 'unknown',
        ]);
        return ['data' => ['acknowledged' => true]];
    }

    // ── ZESA ────────────────────────────────────────────

    protected function handleZesaDataExchange(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;
        $meterNumber = $data['meter_number'] ?? '';

        if ($trigger === 'verify_meter_number') {
            $result = Cache::remember("validation/$meterNumber", 360, function () use ($meterNumber) {
                return $this->meterService->validate($meterNumber);
            });

            return $this->screenResponse('ZESA_SCREEN', [
                'meter_valid' => $result['valid'] ?? false,
                'customer_name' => $result['name'] ?? '',
                'customer_address' => $result['address'] ?? '',
                'meter_currency' => $result['currency'] ?? 'ZWG',
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid meter number.',
            ]);
        }

        if ($trigger === 'buy_zesa') {
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

            return $this->buildSuccessResponse($flowToken, [
                'success' => true,
                'message' => "Your ZESA purchase of {$amount} ZWG for meter {$meterNumber} is being processed.",
                'close_flow' => true,
            ]);
        }

        return $this->screenResponse('ZESA_SCREEN', ['error_message' => 'Please enter a meter number.']);
    }

    // ── Airtime ─────────────────────────────────────────

    protected function handleBuyAirtime(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger !== 'buy_airtime') {
            return $this->screenResponse('AIRTIME_SCREEN', ['error_message' => 'Invalid action.']);
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
            return $this->screenResponse('AIRTIME_SCREEN', ['error_message' => 'Please fill in all required fields.']);
        }

        $params = [
            'type' => 'airtime',
            'handler' => 'AIRTIME',
            'receiver' => $receiver,
            'payment_method' => $paymentMethod,
            'phone' => $phone,
            'amount' => $amount,
            'email' => $email,
            'currency' => $currency,
            'network' => $this->detectNetwork($receiver),
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

        return $this->buildSuccessResponse($flowToken, [
            'success' => true,
            'message' => "Your airtime purchase is being processed.",
            'close_flow' => true,
        ]);
    }

    // ── Bundles ─────────────────────────────────────────

    protected function handleBuyBundle(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger !== 'buy_bundle') {
            return $this->screenResponse('BUNDLES_SCREEN', ['error_message' => 'Invalid action.']);
        }

        $network = $data['network'] ?? '';
        $phoneNumber = $data['phone_number'] ?? '';
        $bundleSize = $data['bundle_size'] ?? '';
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;

        if (!$network || !$phoneNumber || !$bundleSize) {
            return $this->screenResponse('BUNDLES_SCREEN', ['error_message' => 'Please fill in all required fields.']);
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

        return $this->buildSuccessResponse($flowToken, [
            'success' => true,
            'message' => "Your bundle purchase is being processed.",
            'close_flow' => true,
        ]);
    }

    // ── TelOne ──────────────────────────────────────────

    protected function handleTeloneDataExchange(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_telone_account') {
            $accountNumber = $data['account_number'] ?? '';
            $currency = $data['currency'] ?? 'ZWG';
            $screen = $currency === 'USD' ? 'TELONE_USD_SCREEN' : 'TELONE_SCREEN';

            $result = Cache::remember("telone_validation/$accountNumber/$currency", 360, function () use ($accountNumber, $currency) {
                return $this->backend->validate([
                    'handler' => $currency === 'USD' ? 'TELONE_USD' : 'TELONE',
                    'biller_account' => $accountNumber,
                ]);
            });

            return $this->screenResponse($screen, [
                'account_valid' => $result['valid'] ?? false,
                'customer_name' => $result['name'] ?? '',
                'customer_address' => $result['address'] ?? '',
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid TelOne account number.',
            ]);
        }

        if ($trigger === 'buy_telone' || $trigger === 'buy_telone_usd') {
            $accountNumber = $data['biller_account'] ?? '';
            $phoneNumber = $data['phone_number'] ?? '';
            $package = $data['package'] ?? '';
            $currency = $data['currency'] ?? 'ZWG';
            $ecocashNumber = $data['payment_account'] ?? $agent->ecocash_number;

            if (!$accountNumber || !$phoneNumber || !$package) {
                $screen = $currency === 'USD' ? 'TELONE_USD_SCREEN' : 'TELONE_SCREEN';
                return $this->screenResponse($screen, ['error_message' => 'Please fill in all required fields.']);
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

            return $this->buildSuccessResponse($flowToken, [
                'success' => true,
                'message' => "Your TelOne WiFi purchase is being processed.",
                'close_flow' => true,
            ]);
        }

        return $this->screenResponse('TELONE_SCREEN', ['error_message' => 'Invalid action.']);
    }

    // ── Billers ─────────────────────────────────────────

    protected function handleBillerDataExchange(Agent $agent, array $data, string $flowToken): array
    {
        $trigger = $data['trigger'] ?? null;

        if ($trigger === 'verify_biller_account') {
            $billerName = $data['biller_name'] ?? '';
            $accountNumber = $data['account_number'] ?? '';

            $result = Cache::remember("biller_validation/$billerName/$accountNumber", 360, function () use ($billerName, $accountNumber) {
                return $this->backend->validate([
                    'handler' => 'BILLERS',
                    'biller_name' => $billerName,
                    'biller_account' => $accountNumber,
                ]);
            });

            return $this->screenResponse('BILLERS_SCREEN', [
                'account_valid' => $result['valid'] ?? false,
                'customer_name' => $result['name'] ?? '',
                'customer_address' => $result['address'] ?? '',
                'error_message' => ($result['valid'] ?? false) ? '' : 'Invalid biller account.',
            ]);
        }

        if ($trigger === 'pay_biller') {
            $billerName = $data['biller_name'] ?? '';
            $accountNumber = $data['biller_account'] ?? '';
            $amount = (float)($data['amount'] ?? 0);
            $currency = $data['currency'] ?? 'ZWG';
            $ecocashNumber = $data['payment_account'] ?? $agent->ecocash_number;

            if (!$billerName || !$accountNumber || !$amount) {
                return $this->screenResponse('BILLERS_SCREEN', ['error_message' => 'Please fill in all required fields.']);
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

            return $this->buildSuccessResponse($flowToken, [
                'success' => true,
                'message' => "Your payment is being processed.",
                'close_flow' => true,
            ]);
        }

        return $this->screenResponse('BILLERS_SCREEN', ['error_message' => 'Invalid action.']);
    }

    // ── Helpers ─────────────────────────────────────────

    protected function resolveCustomerAgent(string $flowToken): Agent
    {
        $tokenData = $this->parseFlowToken($flowToken);
        return $this->resolveAgent($tokenData);
    }

    protected function screenResponse(string $screen, array $data): array
    {
        return ['screen' => $screen, 'data' => $data];
    }

    protected function detectNetwork(string $phoneNumber): string
    {
        $digits = preg_replace('/\D/', '', $phoneNumber);

        if (str_starts_with($digits, '26371')) {
            return 'netone';
        }

        return 'econet';
    }
}
