<?php

namespace App\Services\Conversation;

use App\Models\Agent;
use App\Services\BackendManager;
use App\Services\MeterValidationService;
use App\Services\WhatsAppService;
use Illuminate\Support\Str;

class ConversationHandler
{
    use ZesaConversationHandler;
    use SettingsConversationHandler;

    protected WhatsAppService $whatsapp;
    protected MeterValidationService $meterService;
    protected BackendManager $backend;

    public function __construct(WhatsAppService $whatsapp, MeterValidationService $meterService, BackendManager $backend)
    {
        $this->whatsapp = $whatsapp;
        $this->meterService = $meterService;
        $this->backend = $backend;
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

    // ── Onboarding ──────────────────────────────────

    /**
     * Check if the agent needs onboarding and start the flow if so.
     * Returns true if onboarding is in progress (caller should stop).
     */
    public function checkOnboarding(Agent $agent): bool
    {
        if (! $agent->needsOnboarding()) {
            return false;
        }

        $this->sendOnboardingPrompt($agent);
        return true;
    }

    /**
     * Send the onboarding greeting and ask for the agent's first name.
     */
    protected function sendOnboardingPrompt(Agent $agent): void
    {
        $this->whatsapp->sendTextMessage(
            $agent->wa_id,
            "👋 *Welcome to Magetsi Agents!*\n\n"
            . "Before we get started, I need a few details.\n\n"
            . "Please type your *first name*:"
        );
    }

    /**
     * Handle text input during the onboarding flow.
     *
     * Step 1: Collect first name  →  save, ask for EcoCash
     * Step 2: Collect EcoCash number  →  save, complete onboarding
     */
    public function handleOnboardingInput(Agent $agent, string $text): void
    {
        $text = trim($text);

        // Step 1: We don't have a real name yet (still default "Agent")
        if ($agent->name === 'Agent' || $agent->name === $agent->wa_id) {
            // Validate: must be letters only, 2-30 chars
            if (! preg_match('/^[a-zA-Z\s\-]{2,30}$/', $text)) {
                $this->whatsapp->sendTextMessage(
                    $agent->wa_id,
                    "❌ That doesn't look like a name. Please type your *first name* (letters only):"
                );
                return;
            }

            $agent->update(['name' => ucfirst(strtolower($text))]);

            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "Nice to meet you, *{$agent->name}*! 😊\n\n"
                . "Now, please type your *EcoCash number* (e.g. 0771234567):"
            );
            return;
        }

        // Step 2: EcoCash number
        $digits = preg_replace('/\D/', '', $text);

        if (strlen($digits) < 10 || strlen($digits) > 12) {
            $this->whatsapp->sendTextMessage(
                $agent->wa_id,
                "❌ That doesn't look like a valid phone number.\n"
                . "Please type your *EcoCash number* (e.g. 0771234567):"
            );
            return;
        }

        // Normalize to local format (0...)
        if (str_starts_with($digits, '263') && strlen($digits) > 9) {
            $digits = '0' . substr($digits, 3);
        }

        $agent->completeOnboarding($agent->name, $digits);

        $this->whatsapp->sendTextMessage(
            $agent->wa_id,
            "✅ *You're all set, {$agent->name}!*\n\n"
            . "EcoCash: {$digits}\n\n"
            . "You can change these anytime from ⚙️ Settings."
        );

        $this->sendWelcome($agent);
    }

    // ── Welcome ─────────────────────────────────────

    /**
     * Send the welcome menu as flow CTA messages.
     *
     * Each action gets its own message with a CTA that directly opens the flow,
     * reducing the interaction from 3 steps to 1 tap.
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
     */
    public function handleTextMessage(Agent $agent, string $text): void
    {
        // If onboarding is in progress, route to onboarding handler
        if ($agent->needsOnboarding()) {
            $this->handleOnboardingInput($agent, $text);
            return;
        }

        $normalized = strtolower(trim($text));

        if (in_array($normalized, ['hi', 'hello', 'hey', 'start', 'menu'])) {
            $this->sendWelcome($agent);
            return;
        }

        if (in_array($normalized, ['zesa'])) {
            $this->launchBuyZesaFlow($agent);
            return;
        }

        if (in_array($normalized, ['settings'])) {
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

        // Default: show welcome
        $this->sendWelcome($agent);
    }

    /**
     * Handle an interactive button reply.
     */
    public function handleButtonReply(Agent $agent, string $buttonId): void
    {
        // Block actions until onboarded
        if ($agent->needsOnboarding()) {
            $this->sendOnboardingPrompt($agent);
            return;
        }

        match ($buttonId) {
            'buy_zesa' => $this->launchBuyZesaFlow($agent),
            'settings' => $this->launchSettingsFlow($agent),
            default => $this->sendWelcome($agent),
        };
    }
}
