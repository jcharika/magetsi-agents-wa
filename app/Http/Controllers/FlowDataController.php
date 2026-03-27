<?php

namespace App\Http\Controllers;

use App\Services\MeterValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FlowDataController extends Controller
{
    protected MeterValidationService $meterService;

    public function __construct(MeterValidationService $meterService)
    {
        $this->meterService = $meterService;
    }

    /**
     * Handle data_exchange requests from WhatsApp Flows.
     *
     * WhatsApp Flows sends encrypted payloads to this endpoint.
     * For simplicity, this handles the decrypted payload directly.
     * In production, implement the decryption as per:
     * https://developers.facebook.com/docs/whatsapp/flows/guides/implementingyourflowendpoint
     */
    public function handle(Request $request): JsonResponse
    {
        $body = $request->all();

        Log::info('Flow data exchange', ['body' => $body]);

        $action = $body['action'] ?? '';
        $screen = $body['screen'] ?? '';
        $data = $body['data'] ?? [];

        return match ($action) {
            'INIT' => $this->handleInit($screen, $data),
            'data_exchange' => $this->handleDataExchange($screen, $data),
            default => response()->json(['version' => '3.0', 'screen' => 'SUCCESS', 'data' => []]),
        };
    }

    /**
     * Handle flow initialization — provide default screen data.
     */
    protected function handleInit(string $screen, array $data): JsonResponse
    {
        return response()->json([
            'version' => '3.0',
            'screen' => $screen ?: 'BUY_ZESA_SCREEN',
            'data' => $data,
        ]);
    }

    /**
     * Handle data exchange during the flow.
     */
    protected function handleDataExchange(string $screen, array $data): JsonResponse
    {
        // Meter validation within the flow
        if (isset($data['meter_number'])) {
            $result = $this->meterService->validate($data['meter_number']);

            return response()->json([
                'version' => '3.0',
                'screen' => $screen,
                'data' => [
                    'meter_valid' => $result['valid'],
                    'customer_name' => $result['name'] ?? '',
                    'customer_address' => $result['address'] ?? '',
                    'error_message' => $result['error'] ?? '',
                ],
            ]);
        }

        return response()->json([
            'version' => '3.0',
            'screen' => $screen,
            'data' => $data,
        ]);
    }
}
