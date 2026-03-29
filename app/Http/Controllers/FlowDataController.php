<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\FlowEncryptionService;
use App\Services\MagetsiApiService;
use App\Services\MeterValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Flows Data Endpoint.
 *
 * Receives encrypted requests from WhatsApp, decrypts them,
 * processes the business logic (INIT, data_exchange, ping, error),
 * and returns an encrypted response.
 *
 * @see https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint/
 */
class FlowDataController extends Controller
{
    public function __construct(
        protected FlowEncryptionService $encryption,
        protected MeterValidationService $meterService,
        protected MagetsiApiService $magetsi,
    ) {}

    /**
     * Handle incoming encrypted flow data exchange request.
     */
    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();

        // 1. Verify signature (X-Hub-Signature-256)
        $signature = $request->header('X-Hub-Signature-256', '');
        if ($signature && ! $this->encryption->verifySignature($rawBody, $signature)) {
            Log::warning('Flow endpoint: signature verification failed.');
            return response('Invalid signature', 432);
        }

        $body = $request->all();

        // 2. Check for required encrypted fields
        $encryptedFlowData = $body['encrypted_flow_data'] ?? null;
        $encryptedAesKey = $body['encrypted_aes_key'] ?? null;
        $initialVector = $body['initial_vector'] ?? null;

        if (! $encryptedFlowData || ! $encryptedAesKey || ! $initialVector) {
            Log::error('Flow endpoint: missing encrypted fields.');
            return response('Missing encryption fields', 400);
        }

        // 3. Decrypt the request
        try {
            $result = $this->encryption->decryptRequest(
                $encryptedFlowData,
                $encryptedAesKey,
                $initialVector
            );
        } catch (\RuntimeException $e) {
            Log::error('Flow endpoint: decryption failed.', ['error' => $e->getMessage()]);
            return response('Decryption failed', 421);
        }

        $decryptedData = $result['decrypted_data'];
        $aesKey = $result['aes_key'];
        $iv = $result['iv'];

        Log::info('Flow endpoint: decrypted request', ['data' => $decryptedData]);

        // 4. Route the request by action
        $action = $decryptedData['action'] ?? '';
        $screen = $decryptedData['screen'] ?? '';
        $data = $decryptedData['data'] ?? [];
        $flowToken = $decryptedData['flow_token'] ?? '';

        $responsePayload = match ($action) {
            'ping' => $this->handlePing(),
            'INIT' => $this->handleInit($screen, $data, $flowToken),
            'BACK' => $this->handleBack($screen, $data, $flowToken),
            'data_exchange' => $this->handleDataExchange($screen, $data, $flowToken),
            default => $this->handleErrorNotification($action, $data),
        };

        Log::info('Flow endpoint: response payload', ['payload' => $responsePayload]);

        // 5. Encrypt and return as plain text
        $encryptedResponse = $this->encryption->encryptResponse($responsePayload, $aesKey, $iv);

        return response($encryptedResponse, 200)
            ->header('Content-Type', 'text/plain');
    }

    // ── Action Handlers ──────────────────────────────────

    /**
     * Health check from WhatsApp.
     */
    protected function handlePing(): array
    {
        return [
            'data' => [
                'status' => 'active',
            ],
        ];
    }

    /**
     * Flow initialization — provide the first screen and its data.
     *
     * Called when user opens the flow with flow_action = data_exchange.
     * We use the flow_token to determine which flow and which agent.
     */
    protected function handleInit(string $screen, array $data, string $flowToken): array
    {
        // Decode flow token — we encode agent_id and flow_id in it
        $tokenData = $this->parseFlowToken($flowToken);
        $agent = $this->resolveAgent($tokenData);

        if (($tokenData['flow'] ?? '') === 'buy_zesa' || $screen === 'BUY_ZESA_SCREEN') {
            return $this->initBuyZesa($agent);
        }

        if (($tokenData['flow'] ?? '') === 'settings' || $screen === 'SETTINGS_SCREEN') {
            return $this->initSettings($agent);
        }

        // Default: return the requested screen with data passed through
        return [
            'screen' => $screen ?: 'BUY_ZESA_SCREEN',
            'data' => $data ?: (object) [],
        ];
    }

    /**
     * Back button pressed — refresh the screen data.
     */
    protected function handleBack(string $screen, array $data, string $flowToken): array
    {
        // Treat BACK the same as INIT for our single-screen flows
        return $this->handleInit($screen, $data, $flowToken);
    }

    /**
     * Data exchange — process form data submitted from a screen.
     *
     * For buy_zesa: validates meter, processes the transaction
     * For settings: saves preferences, returns SUCCESS
     */
    protected function handleDataExchange(string $screen, array $data, string $flowToken): array
    {
        $tokenData = $this->parseFlowToken($flowToken);
        $agent = $this->resolveAgent($tokenData);

        // ── Buy ZESA: meter validation (on meter_number change / submit)
        if ($screen === 'BUY_ZESA_SCREEN') {
            return $this->handleBuyZesaExchange($agent, $data, $flowToken);
        }

        // ── Settings: save and complete
        if ($screen === 'SETTINGS_SCREEN') {
            return $this->handleSettingsExchange($agent, $data, $flowToken);
        }

        // Unknown screen — complete with token
        return $this->buildSuccessResponse($flowToken);
    }

    /**
     * Handle error notifications from WhatsApp client.
     */
    protected function handleErrorNotification(string $action, array $data): array
    {
        Log::warning('Flow endpoint: error notification', [
            'action' => $action,
            'error' => $data['error'] ?? 'unknown',
            'error_message' => $data['error_message'] ?? '',
        ]);

        return [
            'data' => [
                'acknowledged' => true,
            ],
        ];
    }

    // ── Buy ZESA Flow ────────────────────────────────────

    /**
     * Initialize the Buy ZESA screen with agent's saved data.
     */
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

    /**
     * Handle data exchange for Buy ZESA screen.
     *
     * If only meter_number is in data → validate meter
     * If full form data → process transaction and return SUCCESS
     */
    protected function handleBuyZesaExchange(Agent $agent, array $data, string $flowToken): array
    {
        // ── Radio button selected → show/hide custom amount field
        if (array_key_exists('selected_amount', $data)) {
            return [
                'screen' => 'BUY_ZESA_SCREEN',
                'data' => [
                    'show_custom_amount' => $data['selected_amount'] === 'other',
                ],
            ];
        }

        $meterNumber = $data['meter_number'] ?? '';
        $amount = $data['amount'] ?? null;

        // If user submitted the full form (has amount), process and complete
        if ($amount !== null && $meterNumber) {
            return $this->processZesaTransaction($agent, $data, $flowToken);
        }

        // Otherwise, this is a meter validation request
        if ($meterNumber) {
            $result = $this->meterService->validate($meterNumber);

            if ($result['valid']) {
                return [
                    'screen' => 'BUY_ZESA_SCREEN',
                    'data' => [
                        'meter_valid' => true,
                        'customer_name' => $result['name'] ?? '',
                        'customer_address' => $result['address'] ?? '',
                        'meter_currency' => $result['currency'] ?? '',
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

    /**
     * Process a ZESA transaction via Magetsi API and return SUCCESS.
     */
    protected function processZesaTransaction(Agent $agent, array $data, string $flowToken): array
    {
        $meterNumber = $data['meter_number'];
        $amount = $data['amount'] === 'other'
            ? (float) ($data['custom_amount'] ?? 0)
            : (float) $data['amount'];
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;
        $recipientPhone = $data['recipient_phone'] ?? null;

        // Step 1: Validate meter (also gets trace)
        $meterResult = $this->meterService->validate($meterNumber);
        if (! $meterResult['valid']) {
            return [
                'screen' => 'BUY_ZESA_SCREEN',
                'data' => [
                    'error_message' => $meterResult['error'] ?? 'Meter validation failed.',
                ],
            ];
        }

        $trace = $meterResult['trace'];
        $currency = $meterResult['currency'] ?? 'USD';
        $recipientName = $meterResult['name'];
        $recipientAddress = $meterResult['address'];
        $recipientCurrency = $meterResult['recipient_currency'] ?? $currency;

        // Find EcoCash config
        $ecocashConfig = collect($meterResult['debit'] ?? [])
            ->firstWhere('handler', 'ECOCASH');

        if (! $ecocashConfig) {
            return [
                'screen' => 'BUY_ZESA_SCREEN',
                'data' => ['error_message' => 'EcoCash payment not available for this meter.'],
            ];
        }

        $payment = [$this->magetsi->buildEcocashPayment($ecocashNumber, $amount, $currency, $ecocashConfig)];
        $guestId = "Agent {$agent->id}";

        // Step 2: Confirm
        $confirmation = $this->magetsi->confirm(
            'ZESA', $trace, $meterNumber, $amount, $currency,
            $recipientName, $recipientAddress, $recipientCurrency,
            $payment, $guestId
        );

        if (! $confirmation['success']) {
            return [
                'screen' => 'BUY_ZESA_SCREEN',
                'data' => ['error_message' => $confirmation['error'] ?? 'Transaction confirmation failed.'],
            ];
        }

        // Step 3: Process
        $confirmedPayment = $confirmation['payment'] ?? $payment;
        $processPayment = [];
        foreach ($confirmedPayment as $p) {
            $processPayment[] = $this->magetsi->buildEcocashPayment(
                $p['account'] ?? $ecocashNumber,
                $p['amount'] ?? $amount,
                $p['currency'] ?? $currency,
                $ecocashConfig
            );
        }

        $processResult = $this->magetsi->process(
            'ZESA', $trace, $meterNumber, $amount, $currency,
            $recipientName, $recipientAddress, $recipientCurrency,
            $processPayment, $guestId
        );

        if (! $processResult['success']) {
            return [
                'screen' => 'BUY_ZESA_SCREEN',
                'data' => ['error_message' => $processResult['error'] ?? 'Transaction processing failed.'],
            ];
        }

        $txn = $processResult['transaction'] ?? [];

        // Return SUCCESS to end the flow
        return $this->buildSuccessResponse($flowToken, [
            'meter_number' => $meterNumber,
            'customer_name' => $recipientName,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $txn['status'] ?? 'PENDING',
            'reference' => $txn['customer_reference'] ?? $txn['uid'] ?? '',
            'trace' => $trace,
        ]);
    }

    // ── Settings Flow ────────────────────────────────────

    /**
     * Initialize the Settings screen with agent's current preferences.
     */
    protected function initSettings(Agent $agent): array
    {
        $product = $agent->getProductOrDefault('zesa');

        return [
            'screen' => 'SETTINGS_SCREEN',
            'data' => [
                'ecocash_number' => $agent->ecocash_number ?? '',
                'amount_1' => (string) ($product['quick_amounts'][0] ?? 100),
                'amount_2' => (string) ($product['quick_amounts'][1] ?? 200),
                'amount_3' => (string) ($product['quick_amounts'][2] ?? 300),
                'amount_4' => (string) ($product['quick_amounts'][3] ?? 500),
            ],
        ];
    }

    /**
     * Handle settings form submission — save and return SUCCESS.
     */
    protected function handleSettingsExchange(Agent $agent, array $data, string $flowToken): array
    {
        if (isset($data['ecocash_number']) && $data['ecocash_number']) {
            $agent->update(['ecocash_number' => $data['ecocash_number']]);
        }

        $amounts = array_filter([
            $data['amount_1'] ?? null,
            $data['amount_2'] ?? null,
            $data['amount_3'] ?? null,
            $data['amount_4'] ?? null,
        ]);

        if (count($amounts) === 4) {
            $agent->products()->updateOrCreate(
                ['product_id' => 'zesa'],
                [
                    'label' => 'ZESA Tokens',
                    'icon' => '⚡',
                    'currency' => 'ZWG',
                    'min_amount' => 100,
                    'quick_amounts' => array_map('intval', $amounts),
                ]
            );
        }

        return $this->buildSuccessResponse($flowToken, [
            'settings_saved' => true,
            'data' => $data,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────

    /**
     * Build the SUCCESS response to terminate a flow.
     */
    protected function buildSuccessResponse(string $flowToken, array $extraParams = []): array
    {
        return [
            'screen' => 'SUCCESS',
            'data' => [
                'extension_message_response' => [
                    'params' => array_merge(
                        ['flow_token' => $flowToken],
                        $extraParams
                    ),
                ],
            ],
        ];
    }

    /**
     * Parse a flow token to extract agent_id and flow identifier.
     *
     * Token format: "{agent_wa_id}:{flow_id}:{uuid}"
     */
    protected function parseFlowToken(string $flowToken): array
    {
        $parts = explode(':', $flowToken, 3);

        return [
            'wa_id' => $parts[0] ?? '',
            'flow' => $parts[1] ?? '',
            'session' => $parts[2] ?? $flowToken,
        ];
    }

    /**
     * Resolve an Agent from the flow token data.
     */
    protected function resolveAgent(array $tokenData): Agent
    {
        if (! empty($tokenData['wa_id'])) {
            $agent = Agent::where('wa_id', $tokenData['wa_id'])->first();
            if ($agent) {
                return $agent;
            }
        }

        // Fallback to the default simulator agent
        return Agent::firstOrCreate(
            ['phone' => $tokenData['wa_id']],
            ['name' => 'Tinashe', 'wa_id' => '263771234567', 'ecocash_number' => '0771234567']
        );
    }
}
