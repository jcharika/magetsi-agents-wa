<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\FlowDataController\CustomerFlowHandler;
use App\Http\Controllers\Traits\FlowDataController\FlowDataControllerShared;
use App\Models\Customer;
use App\Services\CustomerFlow\CustomerFlowDispatcher;
use App\Services\FlowEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CustomerFlowController extends Controller
{
    use FlowDataControllerShared;
    use CustomerFlowHandler;

    protected FlowEncryptionService $encryption;

    public function __construct(
        protected CustomerFlowDispatcher $customerFlowDispatcher,
    )
    {
        $this->encryption = FlowEncryptionService::forCustomer();
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

            Log::debug('CustomerFlow: request', ['action' => $action, 'screen' => $screen, 'data' => $data, 'flowToken' => $flowToken]);

            $responsePayload = match ($action) {
                'INIT' => $this->handleCustomerInit($screen, $data, $this->resolveCustomer($flowToken)),
                'ping' => $this->handlePing(),
                'navigate' => $this->handleCustomerNavigate($screen, $this->resolveCustomer($flowToken)),
                'data_exchange' => $this->wrapDataExchangeResponse(
                    $this->handleCustomerDataExchange($screen, $data, $this->resolveCustomer($flowToken), $flowToken)
                ),
                'BACK' => $this->handleCustomerBack($screen, $data, $flowToken),
                default => $this->handleErrorNotification($action, $data),
            };

            if (!is_array($responsePayload)) {
                throw new \RuntimeException('Invalid response payload');
            }

            $logScreen = $responsePayload['screen']
                ?? (isset($responsePayload['response']['screen']) ? $responsePayload['response']['screen'] : null)
                ?? '?';
            if (is_array($logScreen)) {
                $logScreen = $logScreen['type'] ?? 'completion';
            }
            $logDataKeys = array_keys(
                $responsePayload['data']
                    ?? (isset($responsePayload['response']['data']) ? $responsePayload['response']['data'] : [])
            );
            Log::debug('CustomerFlow: response payload', ['screen' => $logScreen, 'data_keys' => $logDataKeys]);

            $encryptedResponse = $this->encryption->encryptResponse($responsePayload, $aesKey, $iv);

            return response($encryptedResponse, 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Throwable $exception) {
            Log::error('CustomerFlow: error', ['error' => $exception->getMessage()]);
            return response('Internal server error', 204);
        }
    }

    protected function customerFlowDispatcher(): CustomerFlowDispatcher
    {
        return $this->customerFlowDispatcher;
    }

    protected function handlePing(): array
    {
        return ['data' => ['status' => 'active']];
    }

    protected function resolveCustomer(string $flowToken): Customer
    {
        return $this->resolveCustomerFromToken(
            $this->parseFlowToken($flowToken)
        );
    }

    protected function handleCustomerNavigate(string $screen, Customer $customer): array
    {
        $screen = str_replace('BUY_ZESA_SCREEN', 'ZESA_SCREEN', $screen);
        Log::debug('CustomerFlow: navigate', ['screen' => $screen]);

        return $this->handleCustomerInit($screen, [], $customer);
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
            'data' => [
                'enabled_services' => $this->buildServiceNavItems(),
            ],
        ];
    }

}
