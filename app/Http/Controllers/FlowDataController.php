<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\BackendManager;
use App\Services\FlowEncryptionService;
use App\Services\MeterValidationService;
use App\Http\Controllers\Traits\FlowDataController\BuyZesaFlowHandler;
use App\Http\Controllers\Traits\FlowDataController\SettingsFlowHandler;
use App\Http\Controllers\Traits\FlowDataController\FlowDataControllerShared;
use App\Http\Controllers\Traits\FlowDataController\CustomerFlowHandler;
use App\Http\Controllers\Traits\UsesCustomerFlow;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FlowDataController extends Controller
{
    use FlowDataControllerShared;
    use BuyZesaFlowHandler;
    use SettingsFlowHandler;
    use CustomerFlowHandler;
    use UsesCustomerFlow;

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
                Log::warning('Flow endpoint: signature verification failed.');
                return response('Invalid signature', 432);
            }

            $body = $request->all();

            $encryptedFlowData = $body['encrypted_flow_data'] ?? null;
            $encryptedAesKey = $body['encrypted_aes_key'] ?? null;
            $initialVector = $body['initial_vector'] ?? null;

            if (!$encryptedFlowData || !$encryptedAesKey || !$initialVector) {
                Log::error('Flow endpoint: missing encrypted fields.');
                return response('Missing encryption fields', 400);
            }

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

            if (!is_array($responsePayload)) {
                throw new \RuntimeException('Invalid response payload - not an array');
            }

            Log::info('Flow endpoint: response payload', ['payload' => $responsePayload]);

            $encryptedResponse = $this->encryption->encryptResponse($responsePayload, $aesKey, $iv);

            return response($encryptedResponse, 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Throwable $exception) {
            Log::error('Flow endpoint: error', ['error' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);

            return response('Internal server error', 204);
        }
    }

    protected function handlePing(): array
    {
        return ['data' => ['status' => 'active']];
    }

    protected function handleInit(string $screen, array $data, string $flowToken): array
    {
        $tokenData = $this->parseFlowToken($flowToken);
        $agent = $this->resolveAgent($tokenData);

        $isCustomerFlow = $this->shouldUseCustomerFlow($tokenData['flow'] ?? '');

        if ($isCustomerFlow) {
            return $this->handleCustomerInit($screen, $data, $agent);
        }

        if (($tokenData['flow'] ?? '') === 'buy_zesa' || $screen === 'BUY_ZESA_SCREEN') {
            return $this->initBuyZesa($agent);
        }

        if (($tokenData['flow'] ?? '') === 'settings' || $screen === 'SETTINGS_SCREEN') {
            return $this->initSettings($agent);
        }

        return [
            'screen' => $screen ?: 'BUY_ZESA_SCREEN',
            'data' => $data ?: (object)[],
        ];
    }

    protected function handleBack(string $screen, array $data, string $flowToken): array
    {
        return $this->handleInit($screen, $data, $flowToken);
    }

    protected function handleDataExchange(string $screen, array $data, string $flowToken): array
    {
        $tokenData = $this->parseFlowToken($flowToken);
        $agent = $this->resolveAgent($tokenData);

        if ($this->shouldUseCustomerFlow($tokenData['flow'] ?? '')) {
            return $this->handleCustomerDataExchange($screen, $data, $agent, $flowToken);
        }

        if ($screen === 'BUY_ZESA_SCREEN') {
            return $this->handleBuyZesaExchange($agent, $data, $flowToken);
        }

        if ($screen === 'SETTINGS_SCREEN') {
            return $this->handleSettingsExchange($agent, $data, $flowToken);
        }

        return $this->buildSuccessResponse($flowToken);
    }

    protected function handleErrorNotification(string $action, array $data): array
    {
        Log::warning('Flow endpoint: error notification', [
            'action' => $action,
            'error' => $data['error'] ?? 'unknown',
            'error_message' => $data['error_message'] ?? '',
        ]);

        return ['data' => ['acknowledged' => true]];
    }

    protected function meterService(): MeterValidationService
    {
        return $this->meterService;
    }

    protected function backend(): BackendManager
    {
        return $this->backend;
    }
}
