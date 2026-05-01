<?php

namespace App\Services\Conversation;

use App\Models\Agent;
use App\Services\BackendManager;
use App\Services\MeterValidationService;
use App\Services\MockState;
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
     *
     * @param bool $isFirstWelcome If true, shows Settings button (for new agents)
     */
    public function sendWelcome(Agent $agent, bool $isFirstWelcome = false): void
    {
        $this->whatsapp->sendTextMessage(
            $agent->wa_id,
            "👋 Hi *{$agent->name}*! What would you like to do?"
        );

        $this->launchBuyZesaFlow($agent);

        if ($isFirstWelcome) {
            $this->launchSettingsFlow($agent);
        }

        $this->launchHelpButton($agent, $isFirstWelcome);
    }

    public function launchHelpButton(Agent $agent, bool $isFirstWelcome = false): void
    {
        $buttons = [
            ['id' => 'help', 'title' => '❓ Help & Guide'],
            ['id' => 'support', 'title' => '📞 Contact Support'],
        ];

        if ($isFirstWelcome) {
            $buttons[] = ['id' => 'settings', 'title' => '⚙️ Settings'];
        }

        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            "Need help? Here's what you can do:",
            $buttons
        );
    }

    public function sendHelp(Agent $agent, ConversationSession $session): void
    {
        if ($session->isActive()) {
            $this->sendContextualHelp($agent, $session);
            return;
        }

        $helpText = "❓ *Help Guide*\n\n"
            . "*Quick Commands:*\n"
            . "• `zesa` - Buy electricity tokens\n"
            . "• `settings` - Manage your account\n"
            . "• `help` - Show this help guide\n\n"
            . "*Other Ways:*\n"
            . "• Send an 11-digit meter number to look it up\n"
            . "• Type `menu` to see the main menu\n"
            . "• Type `stop` to cancel current action\n\n"
            . "*Tips:*\n"
            . "• Have your meter number ready\n"
            . "• Ensure you have enough EcoCash balance\n"
            . "• Reply `0` to go back to previous step";

        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            $helpText,
            [
                ['id' => 'support', 'title' => '📞 Contact Support'],
                ['id' => 'buy_zesa', 'title' => '⚡ Buy ZESA'],
                ['id' => 'main_menu', 'title' => '🏠 Main Menu'],
            ]
        );
    }

    public function sendSupport(Agent $agent): void
    {
        $supportText = "👋 To reach our customer support team open the link below:\n\n"
            . "🔗 https://wa.me/263782004005";

        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            $supportText,
            [
                ['id' => 'main_menu', 'title' => '🏠 Main Menu'],
            ]
        );
    }

    protected function sendContextualHelp(Agent $agent, ConversationSession $session): void
    {
        $flowName = $session->flow;
        $currentStep = $session->step;

        $helpByFlow = [
            'onboarding' => [
                'step:name' => "We're setting up your account. What's your name?",
                'step:ecocash' => "Enter your EcoCash number (e.g., 077...).",
                'step:confirm' => "Reply YES to confirm or NO to redo.",
            ],
            'buy_zesa' => [
                'step:meter' => "Enter the 11-digit meter number (e.g., 12345678901).",
                'step:amount' => "Select a quick amount or enter a custom value.",
                'step:phone' => "Enter a phone number for SMS delivery (optional).",
                'step:confirm' => "Reply YES to confirm or NO to cancel.",
            ],
            'settings' => [
                'step:profile' => "Reply with your name to update it.",
                'step:ecocash' => "Enter your EcoCash number.",
                'step:confirm' => "Reply YES to save or NO to cancel.",
            ],
        ];

        $flowHelps = $helpByFlow[$flowName] ?? [];
        $stepHelp = $flowHelps[$currentStep] ?? $flowHelps['step:' . $currentStep] ?? null;

        if ($stepHelp) {
            $hintText = "❓ *Current: " . ucfirst($flowName) . "*\n\n"
                . "{$stepHelp}\n\n"
                . "*Options:*\n"
                . "• Reply with your answer\n"
                . "• Type `0` to go back\n"
                . "• Type `help` for full guide\n"
                . "• Type `menu` to return to main menu";
        } else {
            $hintText = "❓ *Currently in: " . ucfirst($flowName) . "*\n\n"
                . "You're in the middle of a flow.\n"
                . "• Complete the current step\n"
                . "• Type `0` to go back\n"
                . "• Type `help` for more options";
        }

        $this->whatsapp->sendTextMessage($agent->wa_id, $hintText);
    }

    public function cancelFlow(Agent $agent, ConversationSession $session): void
    {
        if (!$session->isActive()) {
            $this->whatsapp->sendTextMessage($agent->wa_id, "Nothing to cancel. You're at the main menu.");
            return;
        }

        $flowName = ucfirst($session->flow);
        $session->reset($agent->wa_id);

        $this->whatsapp->sendTextMessage(
            $agent->wa_id,
            "❌ *Cancelled*\n\nThe {$flowName} flow has been cancelled.\n\nUse the buttons below to continue:"
        );

        $this->launchBuyZesaFlow($agent);
        $this->launchSettingsFlow($agent);
        $this->launchHelpButton($agent);
    }

    public function addHelpHint(string $message): string
    {
        $hint = "\n\n💡 *Tip:* Reply `help` for assistance or `stop` to cancel.";
        return $message . $hint;
    }

    // ── Mock Backend Toggle ────────────────────────────

    protected function handleMockCommand(Agent $agent, string $text): void
    {
        $password = config('magetsi.mock_password', 'mock123');
        $allowedIds = config('magetsi.mock_allowed_wa_ids', []);

        $isAdmin = in_array($agent->wa_id, $allowedIds);

        $parts = explode(' ', trim($text), 3);
        $command = strtolower($parts[1] ?? '');
        $inputPassword = $parts[2] ?? '';

        if (!$isAdmin) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Access Denied*\n\nYou're not authorized to use this command."
            );
            return;
        }

        if (!in_array($command, ['on', 'off', 'enable', 'disable'])) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "📋 *Mock Backend Commands*\n\n"
                . "Usage: `/mock on <password>` or `/mock off <password>`\n\n"
                . "Example: `/mock on mock123`"
            );
            return;
        }

        if ($inputPassword !== $password) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ *Incorrect Password*\n\nPlease check the password and try again."
            );
            return;
        }

        $enable = in_array($command, ['on', 'enable']);

        MockState::toggle($enable);
        $this->backend->reset();

        $status = $enable ? '🟢 ENABLED' : '🔴 DISABLED';
        $currentBackend = MockState::isEnabled() ? 'Mock Backend' : 'Real Backend';

        $this->whatsapp->sendTextMessage(
            $agent->wa_id,
            "✅ *Mock Backend {$status}*\n\n"
            . "Active backend: *{$currentBackend}*\n\n"
            . "Transactions will " . ($enable ? 'use the mock (always succeed with fake tokens).' : 'go to the real backend.')
        );
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

        // Mock backend toggle command: /mock on <password> or /mock off <password>
        if (str_starts_with($normalized, '/mock')) {
            $this->handleMockCommand($agent, $text);
            return;
        }

        if (in_array($normalized, ['hi', 'hello', 'hey', 'start', 'menu'])) {
            $this->sendWelcome($agent);
            return;
        }

        if ($normalized === 'stop' || $normalized === 'cancel') {
            $this->cancelFlow($agent, $session);
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

        if ($normalized === 'help') {
            $this->sendHelp($agent, $session);
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
            'help' => $this->sendHelp($agent, $session),
            'support' => $this->sendSupport($agent),
            'main_menu' => $this->sendWelcome($agent),
            default => $this->sendWelcome($agent),
        };
    }
}
