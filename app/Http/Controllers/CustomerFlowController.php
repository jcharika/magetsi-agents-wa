<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\FlowDataController\CustomerFlowHandler;
use App\Http\Controllers\Traits\FlowDataController\FlowDataControllerShared;
use App\Http\Controllers\Traits\UsesCustomerFlow;
use App\Models\Agent;
use App\Services\BackendManager;
use App\Services\FlowEncryptionService;
use App\Services\MeterValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CustomerFlowController extends Controller
{
    use FlowDataControllerShared;
    use CustomerFlowHandler;
    use UsesCustomerFlow;

    public function __construct(
        protected FlowEncryptionService $encryption,
        protected MeterValidationService $meterService,
        protected BackendManager $backend,
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
                Log::error('CustomerFlow: missing encryption fields.');
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

            Log::info('CustomerFlow: request', ['action' => $action, 'screen' => $screen, 'data' => $data, 'flowToken' => $flowToken]);

            $responsePayload = match ($action) {
                'INIT' => $this->handleCustomerInit($screen, $data, $this->resolveCustomerAgent($flowToken)),
                'ping' => $this->handlePing(),
                'navigate' => $this->handleCustomerNavigate($screen, $this->resolveCustomerAgent($flowToken)),
                'data_exchange' => $this->handleCustomerDataExchange($screen, $data, $this->resolveCustomerAgent($flowToken), $flowToken),
                'BACK' => $this->handleCustomerBack($screen, $data, $flowToken),
                default => $this->handleErrorNotification($action, $data),
            };

            if (!is_array($responsePayload)) {
                throw new \RuntimeException('Invalid response payload');
            }

            Log::info('CustomerFlow: response payload', ['screen' => $responsePayload['screen'] ?? '?', 'data_keys' => array_keys($responsePayload['data'] ?? [])]);

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

    protected function resolveCustomerAgent(string $flowToken): Agent
    {
        return $this->resolveAgent(
            $this->parseFlowToken($flowToken)
        );
    }

    protected function backend(): BackendManager
    {
        return $this->backend;
    }

    protected function meterService(): MeterValidationService
    {
        return $this->meterService;
    }

    protected function handleCustomerNavigate(string $screen, Agent $agent): array
    {
        // Normalize screen names
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        Log::info('CustomerFlow: navigate', ['screen' => $screen]);

        return $this->handleCustomerInit($screen, [], $agent);
    }

    protected function handleErrorNotification(string $action, array $data): array
    {
        Log::warning('CustomerFlow: error notification', [
            'action' => $action,
            'error' => $data['error'] ?? 'unknown',
            'error_message' => $data['error_message'] ?? '',
        ]);

        return ['data' => ['acknowledged' => true]];
    }

    protected function handleCustomerBack(string $screen, array $data, string $flowToken): array
    {
        return [
            'screen' => 'HOME_SCREEN',
            'data' => $data,
        ];
    }
}
