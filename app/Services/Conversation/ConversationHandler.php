<?php

namespace App\Services\Conversation;

use App\Models\Agent;
use App\Services\BackendManager;
use App\Services\MeterValidationService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

class ConversationHandler
{
    use ZesaConversationHandler;
    use SettingsConversationHandler;

    public WhatsAppService $whatsapp;
    protected MeterValidationService $meterService;
    protected BackendManager $backend;
    protected FlowEngine $engine;

    public function __construct(
        WhatsAppService $whatsapp,
        MeterValidationService $meterService,
        BackendManager $backend,
        FlowEngine $engine,
    ) {
        $this->whatsapp = $whatsapp;
        $this->meterService = $meterService;
        $this->backend = $backend;
        $this->engine = $engine;
    }

    /**
     * Find or create an agent from a WhatsApp message.
     */
    public function resolveAgent(string $waId, ?string $name = null): Agent
    {
        return Agent::query()->firstOrCreate(
            ['wa_id' => $waId],
            [
                'name' => $name ?? 'Agent',
                'phone' => $waId,
                'onboarded' => false,
            ]
        );
    }

    // ── Session ────────────────────────────────────

    /**
     * Load the conversation session for this agent.
     */
    public function loadSession(Agent $agent): ConversationSession
    {
        return ConversationSession::load($agent->wa_id);
    }

    /**
     * Check if a flow should auto-activate (e.g., onboarding)
     * and start it if so. Returns true if a flow was started.
     */
    public function checkAutoActivation(Agent $agent): bool
    {
        $flow = $this->engine->findActivatableFlow($agent);

        if ($flow) {
            $this->engine->startFlow($agent, $flow, $this);
            return true;
        }

        return false;
    }

    // ── Welcome ─────────────────────────────────────

    /**
     * Send the welcome menu as flow CTA messages.
     */
    public function sendWelcome(Agent $agent): void
    {
        $this->whatsapp->sendTextMessage(
            $agent->wa_id,
            "👋 Hi *{$agent->name}*! What would you like to do?"
        );

        $this->launchBuyZesaFlow($agent);
        $this->launchSettingsFlow($agent);
    }

    // ── Message handlers ────────────────────────────

    /**
     * Handle a text message from the agent.
     *
     * Routing priority:
     *   1. Active session flow (e.g., onboarding step in progress)
     *   2. Auto-activatable flows (e.g., onboarding for new agents)
     *   3. Keyword matching (hi, zesa, settings, meter numbers)
     *   4. Default: show welcome menu
     */
    public function handleTextMessage(Agent $agent, string $text): void
    {
        $text = trim($text);
        $session = $this->loadSession($agent);

        // 1. Active flow in progress — delegate to flow engine
        if ($session->isActive()) {
            $handled = $this->engine->processInput($agent, $session, $text, $this);
            if ($handled) {
                return;
            }
        }

        // 2. Auto-activate a flow if needed (e.g., new agent → onboarding)
        if ($this->checkAutoActivation($agent)) {
            return;
        }

        // 3. Normal keyword routing (agent is onboarded, no active flow)
        $normalized = strtolower($text);

        if (in_array($normalized, ['hi', 'hello', 'hey', 'start', 'menu'])) {
            $this->sendWelcome($agent);
            return;
        }

        if ($normalized === 'zesa') {
            $this->launchBuyZesaFlow($agent);
            return;
        }

        if ($normalized === 'settings') {
            $this->launchSettingsFlow($agent);
            return;
        }

        // Check if it looks like a meter number (11 digits)
        if (preg_match('/^\d{11}$/', $normalized)) {
            $result = $this->meterService->validate($normalized);

            if ($result['valid']) {
                $this->whatsapp->sendTextMessage(
                    $agent->wa_id,
                    "✅ *Meter Found*\n\n"
                    . "Name: {$result['name']}\n"
                    . "Address: {$result['address']}\n"
                    . "Currency: {$result['currency']}\n\n"
                    . "Use the *Buy ZESA* button to purchase tokens."
                );
            } else {
                $this->whatsapp->sendTextMessage(
                    $agent->wa_id,
                    "❌ {$result['error']}"
                );
            }

            $this->sendWelcome($agent);
            return;
        }

        // 4. Default: show welcome
        $this->sendWelcome($agent);
    }

    /**
     * Handle an interactive button reply.
     */
    public function handleButtonReply(Agent $agent, string $buttonId): void
    {
        // If there's an active flow, a button tap shouldn't bypass it
        $session = $this->loadSession($agent);
        if ($session->isActive()) {
            // Auto-activate check handles re-prompting
            return;
        }

        // Block actions until onboarded
        if ($agent->needsOnboarding()) {
            $this->checkAutoActivation($agent);
            return;
        }

        match ($buttonId) {
            'buy_zesa' => $this->launchBuyZesaFlow($agent),
            'settings' => $this->launchSettingsFlow($agent),
            default => $this->sendWelcome($agent),
        };
    }
}
