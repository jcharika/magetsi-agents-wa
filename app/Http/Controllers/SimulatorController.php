<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Conversation\ConversationSession;
use App\Services\Conversation\FlowEngine;
use App\Services\Conversation\SimulatorMessageCollector;
use App\Services\MeterValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SimulatorController extends Controller
{
    protected FlowEngine $engine;

    public function __construct(FlowEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Show the chat simulator landing page.
     */
    public function index()
    {
        return view('simulator', ['mode' => request('mode', 'agent')]);
    }

    /**
     * Simulate sending a message and get bot responses.
     * This bypasses WhatsApp entirely — processes locally and returns the response.
     */
    public function simulate(Request $request): JsonResponse
    {
        $action = $request->input('action');
        $payload = $request->input('payload', []);
        $mode = $request->input('mode', 'agent');

        if ($mode === 'customer') {
            return $this->simulateCustomer($action, $payload);
        }

        // Use the simulator agent — new agents start un-onboarded
        $agent = Agent::firstOrCreate(
            ['phone' => '263771234567'],
            [
                'name' => 'Agent',
                'wa_id' => '263771234567',
                'onboarded' => false,
                'blocked' => false,
            ]
        );

        if ($agent->blocked && $action !== 'start') {
            return response()->json(['messages' => [['type' => 'text', 'text' => "❌ *Account Suspended*\n\nYour access has been suspended. Contact support."]]]);
        }

        $product = $agent->getProductOrDefault('zesa');

        return match ($action) {
            'start' => $this->handleStart($agent),
            'text' => $this->handleText($agent, $payload['text'] ?? 'hi', $product),
            'button' => $this->handleButton($agent, $payload['button_id'] ?? '', $product),
            'flow_complete' => $this->handleFlowComplete($agent, $payload),
            'validate_meter' => $this->handleMeterValidation($payload['meter_number'] ?? ''),
            default => response()->json(['messages' => [['type' => 'text', 'text' => 'Unknown action']]]),
        };
    }

    // ── Customer Mode ───────────────────────────────

    protected function simulateCustomer(string $action, array $payload): JsonResponse
    {
        $customer = Customer::firstOrCreate(
            ['phone' => '263778888888'],
            [
                'name' => 'Customer',
                'wa_id' => '263778888888',
                'blocked' => false,
            ]
        );

        if ($customer->blocked && $action !== 'start') {
            return response()->json(['messages' => [['type' => 'text', 'text' => "❌ *Account Suspended*\n\nYour access has been suspended. Contact support."]]]);
        }

        return match ($action) {
            'start' => response()->json([
                'messages' => $this->customerWelcomeMessages($customer),
            ]),
            'text' => response()->json([
                'messages' => $this->customerWelcomeMessages($customer),
            ]),
            'flow_complete' => $this->handleCustomerFlowComplete($customer, $payload),
            'data_exchange' => $this->handleCustomerDataExchange($payload),
            default => response()->json(['messages' => $this->customerWelcomeMessages($customer)]),
        };
    }

    // ── Session-backed message collector ──────────

    /**
     * Create a message collector "handler" that the FlowEngine can use.
     * It collects messages instead of sending them via WhatsApp.
     */
    protected function createCollectorHandler(Agent $agent): object
    {
        $collector = new SimulatorMessageCollector();

        // Create a handler proxy that looks like ConversationHandler
        // with a whatsapp property and sendWelcome method
        return new class($collector, $agent) {
            public SimulatorMessageCollector $whatsapp;
            private Agent $agent;

            public function __construct(SimulatorMessageCollector $collector, Agent $agent)
            {
                $this->whatsapp = $collector;
                $this->agent = $agent;
            }

            public function sendWelcome(Agent $agent): void
            {
                $this->whatsapp->sendTextMessage(
                    $agent->wa_id,
                    "👋 Hi *{$agent->name}*! What would you like to do?"
                );

                // Add flow CTA messages
                $this->whatsapp->messages[] = [
                    'type' => 'flow',
                    'flow_id' => 'buy_zesa',
                    'cta' => '⚡ Buy ZESA',
                    'text' => 'Purchase ZESA electricity tokens',
                ];
                $this->whatsapp->messages[] = [
                    'type' => 'flow',
                    'flow_id' => 'settings',
                    'cta' => '⚙️ Settings',
                    'text' => 'Update your preferences',
                ];
            }
        };
    }

    // ── Action Handlers ─────────────────────────────

    /**
     * Handle the initial 'start' action — triggers onboarding for new agents.
     */
    protected function handleStart(Agent $agent): JsonResponse
    {
        $session = ConversationSession::load($agent->wa_id);

        // Check if a flow should auto-activate (e.g., onboarding)
        $flow = $this->engine->findActivatableFlow($agent);

        if ($flow && ! $session->isActive()) {
            $handler = $this->createCollectorHandler($agent);
            $session = $this->engine->startFlow($agent, $flow, $handler);

            return response()->json([
                'messages' => $handler->whatsapp->flush(),
                'session' => $this->sessionMeta($session),
            ]);
        }

        return response()->json([
            'messages' => $this->welcomeMessages($agent),
            'session' => $this->sessionMeta($session),
        ]);
    }

    /**
     * Handle text input — delegates to FlowEngine if a session is active.
     */
    protected function handleText(Agent $agent, string $text, array $product): JsonResponse
    {
        $text = trim($text);
        $session = ConversationSession::load($agent->wa_id);

        // 1. Active flow in progress — delegate to flow engine
        if ($session->isActive()) {
            $handler = $this->createCollectorHandler($agent);
            $handled = $this->engine->processInput($agent, $session, $text, $handler);

            if ($handled) {
                // Reload session to get updated state
                $session = ConversationSession::load($agent->wa_id);
                // Refresh agent in case it was updated
                $agent->refresh();

                return response()->json([
                    'messages' => $handler->whatsapp->flush(),
                    'session' => $this->sessionMeta($session),
                ]);
            }
        }

        // 2. Check auto-activation (e.g., onboarding)
        $flow = $this->engine->findActivatableFlow($agent);
        if ($flow) {
            $handler = $this->createCollectorHandler($agent);
            $session = $this->engine->startFlow($agent, $flow, $handler);

            return response()->json([
                'messages' => $handler->whatsapp->flush(),
                'session' => $this->sessionMeta($session),
            ]);
        }

        // 3. Normal message routing (onboarded, no active flow)
        $normalized = strtolower($text);

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

            array_push($messages, ...$this->welcomeMessages($agent));
            return response()->json(['messages' => $messages, 'session' => $this->sessionMeta($session)]);
        }

        return response()->json([
            'messages' => $this->welcomeMessages($agent),
            'session' => $this->sessionMeta($session),
        ]);
    }

    protected function handleButton(Agent $agent, string $buttonId, array $product): JsonResponse
    {
        $session = ConversationSession::load($agent->wa_id);

        // Block actions if an active flow is in progress
        if ($session->isActive()) {
            return $this->handleStart($agent);
        }

        // Block actions until onboarded
        if ($agent->needsOnboarding()) {
            return $this->handleStart($agent);
        }

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
                'session' => $this->sessionMeta($session),
            ]);
        }

        return response()->json([
            'messages' => $this->welcomeMessages($agent),
            'session' => $this->sessionMeta($session),
        ]);
    }

    /**
     * Serve a flow JSON schema merged with agent-specific initial data.
     */
    public function flowSchema(Request $request, string $flowId): JsonResponse
    {
        $mode = $request->input('mode', 'agent');

        // Customer mode uses its own flow file
        if ($mode === 'customer') {
            $path = resource_path("flows/{$flowId}.json");
            if (! File::exists($path)) {
                return response()->json(['error' => 'Flow not found'], 404);
            }
            $schema = json_decode(File::get($path), true);

            $customer = Customer::firstOrCreate(
                ['phone' => '263778888888'],
                ['name' => 'Customer', 'wa_id' => '263778888888', 'blocked' => false]
            );

            return response()->json([
                'schema' => $schema,
                'initial_data' => [
                    'ecocash_number' => '',
                    'enabled_services' => $this->buildSimNavItems(),
                    'airtime_options' => $this->buildSimAirtimeOptions(),
                ],
            ]);
        }

        if ($flowId === 'customer') {
            return response()->json(['error' => 'Customer flow not available in agent mode'], 404);
        }

        $path = resource_path("flows/{$flowId}.json");

        if (! File::exists($path)) {
            return response()->json(['error' => 'Flow not found'], 404);
        }

        $schema = json_decode(File::get($path), true);

        // Load agent data for initial values
        $agent = Agent::firstOrCreate(
            ['phone' => '263771234567'],
            ['name' => 'Agent', 'wa_id' => '263771234567', 'onboarded' => false]
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

    private function buildSimNavItems(): array
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
                    'networks' => [
                        ['id' => 'econet', 'title' => 'Econet'],
                        ['id' => 'netone', 'title' => 'NetOne'],
                    ],
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
            if (!(bool) config("whatsapp.customer_services.{$envSuffix}", true)) continue;

            $iconPath = $iconDir . '/' . $svc['file'];
            $image = '';
            if (file_exists($iconPath)) {
                $ext = pathinfo($iconPath, PATHINFO_EXTENSION);
                $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/' . $ext;
                $image = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($iconPath));
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

    private function buildSimAirtimeOptions(): array
    {
        $iconDir = public_path('images/service-icons');

        $options = [
            'ECONET_USD_SCREEN' => [
                'title' => 'Econet USD Airtime',
                'desc' => 'US Dollar',
                'file' => 'econet_usd.png',
                'alt' => 'Econet USD',
                'network' => 'econet',
                'currency' => 'USD',
            ],
            'ECONET_ZWG_SCREEN' => [
                'title' => 'Econet ZWG Airtime',
                'desc' => 'Zimbabwe Gold',
                'file' => 'econet_zwg.png',
                'alt' => 'Econet ZWG',
                'network' => 'econet',
                'currency' => 'ZWG',
            ],
            'NETONE_USD_SCREEN' => [
                'title' => 'NetOne USD Airtime',
                'desc' => 'US Dollar',
                'file' => 'netone_usd.png',
                'alt' => 'NetOne USD',
                'network' => 'netone',
                'currency' => 'USD',
            ],
        ];

        $items = [];
        foreach ($options as $screenId => $opt) {
            $iconPath = $iconDir . '/' . $opt['file'];
            $image = '';
            if (file_exists($iconPath)) {
                $ext = pathinfo($iconPath, PATHINFO_EXTENSION);
                $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/' . $ext;
                $image = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($iconPath));
            }

            $navPayload = [
                'ecocash_number' => '${data.ecocash_number}',
                'network' => $opt['network'],
                'currency' => $opt['currency'],
            ];
            if ($opt['currency'] === 'USD') {
                $navPayload['email'] = '';
            }

            $items[] = [
                'id' => $screenId,
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
                    'payload' => $navPayload,
                ],
            ];
        }

        return $items;
    }

    /**
     * Handle completed flow submission.
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

        return response()->json(['messages' => $this->welcomeMessages($agent)]);
    }

    /**
     * Full ZESA transaction pipeline using Magetsi API.
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
                    ...$this->welcomeMessages($agent),
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
                    ...$this->welcomeMessages($agent),
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
                ...$this->welcomeMessages($agent),
            ],
        ]);
    }

    /**
     * Handle settings save from the flow.
     */
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
                ...$this->welcomeMessages($agent),
            ],
        ]);
    }

    protected function handleCustomerDataExchange(array $payload): JsonResponse
    {
        $trigger = $payload['trigger'] ?? '';

        if ($trigger === 'verify_meter_number') {
            $meterNumber = $payload['meter_number'] ?? '';

            // Simulate meter validation
            $service = app(\App\Services\MeterValidationService::class);
            $result = $service->validate($meterNumber);

            $currency = $result['currency'] ?? 'ZWG';
            return response()->json([
                'data' => [
                    'meter_valid' => $result['valid'] ?? false,
                    'customer_name' => $result['name'] ?? '',
                    'customer_address' => $result['address'] ?? '',
                    'meter_currency' => $currency,
                    'ecocash_number' => $payload['ecocash_number'] ?? '',
                    'payment_methods' => [
                        ['id' => 'ecocash', 'title' => "EcoCash $currency"],
                        ['id' => 'stripe', 'title' => 'International Card'],
                    ],
                ],
            ]);
        }

        if ($trigger === 'verify_telone_account') {
            return response()->json([
                'data' => [
                    'account_valid' => true,
                    'customer_name' => 'TelOne Customer',
                    'customer_address' => 'Harare',
                    'account_currency' => $payload['currency'] ?? 'ZWG',
                ],
            ]);
        }

        // Handle nav item data_exchange — map triggers to screen init data
        $navScreenMap = [
            'init_zesa' => 'ZESA_SCREEN',
            'init_airtime' => 'AIRTIME_SCREEN',
            'init_bundles' => 'BUNDLES_SCREEN',
            'init_telone' => 'TELONE_HOME_SCREEN',
            'init_billers' => 'BILLERS_SCREEN',
            'init_support' => 'SUPPORT_SCREEN',
        ];
        if (!isset($navScreenMap[$trigger])) {
            // Fallback: map item id directly to screen name (e.g. nav_click with id=AIRTIME_SCREEN)
            $itemId = $payload['id'] ?? '';
            if ($trigger === 'nav_click' && $itemId) {
                $trigger = "init_{$itemId}";
            }
        }

        if (isset($navScreenMap[$trigger])) {
            $screen = $navScreenMap[$trigger];
            $data = match ($screen) {
                'AIRTIME_SCREEN' => [
                    'ecocash_number' => '',
                    'airtime_options' => $this->buildSimAirtimeOptions(),
                ],
                'ZESA_SCREEN' => [
                    'meter_valid' => false,
                    'customer_name' => '',
                    'customer_address' => '',
                    'meter_currency' => 'ZWG',
                    'ecocash_number' => '',
                    'payment_methods' => [
                        ['id' => 'ecocash', 'title' => 'EcoCash ZWG'],
                        ['id' => 'stripe', 'title' => 'International Card'],
                    ],
                ],
                'BUNDLES_SCREEN' => [
                    'networks' => [
                        ['id' => 'econet', 'title' => 'Econet'],
                        ['id' => 'netone', 'title' => 'NetOne'],
                    ],
                ],
                default => [],
            };
            return response()->json([
                'screen' => $screen,
                'data' => $data,
            ]);
        }

        // Default: return empty data
        return response()->json(['data' => []]);
    }

    protected function handleCustomerFlowComplete(Customer $customer, array $data): JsonResponse
    {
        $flowId = $data['flow_id'] ?? '';
        $trigger = $data['trigger'] ?? '';

        // Map completed flow to success message
        $flowNames = [
            'zesa' => 'ZESA Tokens',
            'airtime' => 'Airtime',
            'bundles' => 'Data Bundles',
            'telone' => 'TelOne WiFi',
            'telone_usd' => 'TelOne WiFi (USD)',
            'billers' => 'Bills Payment',
            'support' => 'Support Request',
        ];

        $name = $flowNames[$flowId] ?? ucfirst(str_replace('_', ' ', $flowId));

        return response()->json([
            'messages' => [
                [
                    'type' => 'text',
                    'text' => "✅ *{$name}*\n\nYour request has been submitted successfully and is being processed.",
                ],
                ...$this->customerWelcomeMessages($customer),
            ],
        ]);
    }

    protected function handleMeterValidation(string $meter): JsonResponse
    {
        $service = app(MeterValidationService::class);
        return response()->json($service->validate($meter));
    }

    // ── Helpers ─────────────────────────────────────

    protected function customerWelcomeMessages(Customer $customer): array
    {
        return [
            ['type' => 'text', 'text' => "👋 Hi *{$customer->name}*! How can we help you today?"],
            ['type' => 'flow', 'flow_id' => 'customer', 'cta' => '🏠 Home', 'text' => 'View all Magetsi services'],
        ];
    }

    /**
     * Build the welcome menu as flow CTA messages.
     */
    protected function welcomeMessages(Agent $agent): array
    {
        return [
            ['type' => 'text', 'text' => "👋 Hi *{$agent->name}*! What would you like to do?"],
            ['type' => 'flow', 'flow_id' => 'buy_zesa', 'cta' => '⚡ Buy ZESA', 'text' => 'Purchase ZESA electricity tokens'],
            ['type' => 'flow', 'flow_id' => 'settings', 'cta' => '⚙️ Settings', 'text' => 'Update your preferences'],
        ];
    }

    /**
     * Build session metadata for JSON response.
     */
    protected function sessionMeta(ConversationSession $session): array
    {
        return [
            'flow' => $session->flow,
            'step' => $session->step,
            'active' => $session->isActive(),
        ];
    }
}
