<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Transaction;
use App\Services\MeterValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SimulatorController extends Controller
{
    /**
     * Show the chat simulator landing page.
     */
    public function index()
    {
        return view('simulator');
    }

    /**
     * Simulate sending a message and get bot responses.
     * This bypasses WhatsApp entirely — processes locally and returns the response.
     */
    public function simulate(Request $request): JsonResponse
    {
        $action = $request->input('action');
        $payload = $request->input('payload', []);

        // Use the simulator agent — match by phone (same as the seeded agent)
        $agent = Agent::firstOrCreate(
            ['phone' => '263771234567'],
            [
                'name' => 'Tinashe',
                'wa_id' => '263771234567',
                'ecocash_number' => '0771234567',
            ]
        );

        $product = $agent->getProductOrDefault('zesa');

        return match ($action) {
            'start', 'text' => $this->handleText($agent, $payload['text'] ?? 'hi', $product),
            'button' => $this->handleButton($agent, $payload['button_id'] ?? '', $product),
            'flow_complete' => $this->handleFlowComplete($agent, $payload),
            'validate_meter' => $this->handleMeterValidation($payload['meter_number'] ?? ''),
            default => response()->json(['messages' => [['type' => 'text', 'text' => 'Unknown action']]]),
        };
    }

    protected function handleText(Agent $agent, string $text, array $product): JsonResponse
    {
        $normalized = strtolower(trim($text));

        // Check if it looks like a meter number
        if (preg_match('/^\d{11}$/', $normalized)) {
            $meterService = app(MeterValidationService::class);
            $result = $meterService->validate($normalized);

            $messages = [];
            if ($result['valid']) {
                $messages[] = [
                    'type' => 'text',
                    'text' => "✅ *Meter Found*\n\nName: {$result['name']}\nAddress: {$result['address']}\nCurrency: {$result['currency']}\n\nUse the *Buy ZESA* button to purchase tokens.",
                ];
            } else {
                $messages[] = ['type' => 'text', 'text' => "❌ {$result['error']}"];
            }

            $messages[] = $this->welcomeMessage($agent);
            return response()->json(['messages' => $messages]);
        }

        return response()->json([
            'messages' => [$this->welcomeMessage($agent)],
        ]);
    }

    protected function handleButton(Agent $agent, string $buttonId, array $product): JsonResponse
    {
        // Map button IDs to flow file names
        $flowMap = [
            'buy_zesa' => 'buy_zesa',
            'settings' => 'settings',
        ];

        $flowId = $flowMap[$buttonId] ?? null;

        if ($flowId) {
            return response()->json([
                'messages' => [
                    [
                        'type' => 'flow',
                        'flow_id' => $flowId,
                    ],
                ],
            ]);
        }

        return response()->json([
            'messages' => [$this->welcomeMessage($agent)],
        ]);
    }

    /**
     * Serve a flow JSON schema merged with agent-specific initial data.
     */
    public function flowSchema(Request $request, string $flowId): JsonResponse
    {
        $path = resource_path("flows/{$flowId}.json");

        if (! File::exists($path)) {
            return response()->json(['error' => 'Flow not found'], 404);
        }

        $schema = json_decode(File::get($path), true);

        // Load agent data for initial values
        $agent = Agent::firstOrCreate(
            ['phone' => '263771234567'],
            ['name' => 'Tinashe', 'wa_id' => '263771234567', 'ecocash_number' => '0771234567']
        );
        $product = $agent->getProductOrDefault('zesa');

        // Build initial data from agent context
        $initialData = [
            'ecocash_number' => $agent->ecocash_number ?? '',
            'amount_1' => (string) ($product['quick_amounts'][0] ?? '100'),
            'amount_2' => (string) ($product['quick_amounts'][1] ?? '200'),
            'amount_3' => (string) ($product['quick_amounts'][2] ?? '300'),
            'amount_4' => (string) ($product['quick_amounts'][3] ?? '500'),
            'currency' => $product['currency'] ?? 'ZWG',
            'min_amount' => $product['min_amount'] ?? 100,
            'quick_amounts' => $product['quick_amounts'] ?? [100, 200, 300, 500],
        ];

        return response()->json([
            'schema' => $schema,
            'initial_data' => $initialData,
        ]);
    }

    /**
     * Handle completed flow submission.
     *
     * For buy_zesa: runs the full Magetsi API pipeline (confirm → process).
     * The meter was already validated in the flow (validate_meter action),
     * so we use the cached trace + customer data.
     */
    protected function handleFlowComplete(Agent $agent, array $data): JsonResponse
    {
        $flowId = $data['flow_id'] ?? '';

        if ($flowId === 'buy_zesa') {
            return $this->handleZesaTransaction($agent, $data);
        }

        if ($flowId === 'settings') {
            return $this->handleSettingsSave($agent, $data);
        }

        return response()->json(['messages' => [$this->welcomeMessage($agent)]]);
    }

    /**
     * Full ZESA transaction pipeline using Magetsi API.
     *
     * Flow: validate meter → process transaction → store result.
     * The active backend (new or legacy) handles the specifics.
     */
    protected function handleZesaTransaction(Agent $agent, array $data): JsonResponse
    {
        $backend = app(\App\Services\BackendManager::class);
        $meterService = app(MeterValidationService::class);

        $meterNumber = $data['meter_number'] ?? '';
        $amount = $data['amount'] ?? $data['custom_amount'] ?? 0;
        if ($amount === 'other') {
            $amount = $data['custom_amount'] ?? 0;
        }
        $amount = (float) $amount;
        $ecocashNumber = $data['ecocash_number'] ?? $agent->ecocash_number;
        $recipientPhone = $data['recipient_phone'] ?? null;

        // Step 1: Validate meter (backend-agnostic)
        $meterResult = $meterService->validate($meterNumber);

        if (! $meterResult['valid']) {
            return response()->json([
                'messages' => [
                    ['type' => 'text', 'text' => "❌ *Meter Validation Failed*\n\n{$meterResult['error']}"],
                    $this->welcomeMessage($agent),
                ],
            ]);
        }

        // Step 2: Process transaction (backend-agnostic)
        $result = $backend->processTransaction([
            'meter_number' => $meterResult['meter_number'] ?? $meterNumber,
            'amount' => $amount,
            'currency' => $meterResult['currency'] ?? 'USD',
            'ecocash_number' => $ecocashNumber,
            'recipient_name' => $meterResult['name'],
            'recipient_address' => $meterResult['address'],
            'recipient_currency' => $meterResult['recipient_currency'] ?? $meterResult['currency'] ?? 'USD',
            'trace' => $meterResult['trace'] ?? null,
            'debit' => $meterResult['debit'] ?? [],
            'guest_id' => "Agent {$agent->id}",
            'recipient_phone' => $recipientPhone,
        ]);

        if (! $result['success']) {
            return response()->json([
                'messages' => [
                    ['type' => 'text', 'text' => "❌ *Transaction Failed*\n\n{$result['error']}"],
                    $this->welcomeMessage($agent),
                ],
            ]);
        }

        $txn = $result['transaction'] ?? [];
        $confirmation = $result['confirmation'] ?? [];
        $currency = $meterResult['currency'] ?? 'USD';

        // Store in local DB
        $transaction = Transaction::create([
            'agent_id' => $agent->id,
            'product_id' => 'zesa',
            'handler' => 'ZESA',
            'meter_number' => $meterNumber,
            'customer_name' => $meterResult['name'],
            'customer_address' => $meterResult['address'],
            'amount' => $amount,
            'currency' => $currency,
            'ecocash_number' => $ecocashNumber,
            'recipient_phone' => $recipientPhone,
            'status' => strtolower($txn['status'] ?? 'pending'),
            'trace' => $meterResult['trace'] ?? null,
            'uid' => $txn['uid'] ?? null,
            'external_uid' => $txn['external_uid'] ?? null,
            'biller_status' => $txn['biller_status'] ?? null,
            'payment_status' => $txn['payment_status'] ?? null,
            'payment_amount' => $txn['payment_amount'] ?? null,
            'customer_reference' => $txn['customer_reference'] ?? null,
            'reference' => $txn['reference'] ?? $txn['uid'] ?? null,
            'api_response' => $result['raw_response'] ?? $result,
        ]);

        // Build the success card data
        $successData = [
            ['label' => 'Backend', 'value' => ucfirst($backend->getBackendName())],
            ['label' => 'Meter', 'value' => $meterNumber],
            ['label' => 'Customer', 'value' => $meterResult['name']],
            ['label' => 'Amount', 'value' => "({$currency}) {$amount}"],
        ];

        // Add fee breakdown from confirmation (if available)
        foreach ($confirmation['amounts'] ?? [] as $amountInfo) {
            if (($amountInfo['type'] ?? '') !== 'principal') {
                $successData[] = [
                    'label' => $amountInfo['name'] ?? 'Fee',
                    'value' => "({$amountInfo['currency']}) {$amountInfo['amount']}",
                ];
            }
        }

        $successData[] = ['label' => 'Status', 'value' => ucfirst($txn['status'] ?? 'Processing')];
        $successData[] = ['label' => 'Reference', 'value' => $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—'];

        $smsNote = $recipientPhone ? "\n📱 Token SMS will be sent to {$recipientPhone}" : '';

        return response()->json([
            'messages' => [
                [
                    'type' => 'success',
                    'data' => $successData,
                    'sms_note' => $smsNote,
                ],
                [
                    'type' => 'buttons',
                    'text' => '',
                    'buttons' => [
                        ['id' => 'buy_zesa', 'title' => '⚡ New Transaction'],
                        ['id' => 'settings', 'title' => '⚙️ Settings'],
                    ],
                ],
            ],
        ]);
    }

    protected function handleSettingsSave(Agent $agent, array $data): JsonResponse
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

        return response()->json([
            'messages' => [
                ['type' => 'text', 'text' => "✅ *Settings Saved!*\n\nYour preferences have been updated."],
                $this->welcomeMessage($agent),
            ],
        ]);
    }

    protected function handleMeterValidation(string $meter): JsonResponse
    {
        $service = app(MeterValidationService::class);
        return response()->json($service->validate($meter));
    }

    protected function welcomeMessage(Agent $agent): array
    {
        return [
            'type' => 'buttons',
            'text' => "👋 Welcome back, *{$agent->name}*!\nUse the buttons below to buy ZESA or update your settings.",
            'header' => 'Magetsi Agents',
            'buttons' => [
                ['id' => 'buy_zesa', 'title' => '⚡ Buy ZESA'],
                ['id' => 'settings', 'title' => '⚙️ Settings'],
            ],
        ];
    }
}
