<?php

namespace App\Services\Conversation;

use App\Models\Agent;
use App\Services\MagetsiApiService;
use App\Services\MeterValidationService;
use App\Services\WhatsAppService;

class ConversationHandler
{
    use ZesaConversationHandler;
    use SettingsConversationHandler;

    protected WhatsAppService $whatsapp;
    protected MeterValidationService $meterService;
    protected MagetsiApiService $magetsi;

    public function __construct(WhatsAppService $whatsapp, MeterValidationService $meterService, MagetsiApiService $magetsi)
    {
        $this->whatsapp = $whatsapp;
        $this->meterService = $meterService;
        $this->magetsi = $magetsi;
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
            ]
        );
    }

    /**
     * Send the welcome message with interactive buttons.
     */
    public function sendWelcome(Agent $agent): void
    {
        $this->whatsapp->sendInteractiveButtons(
            $agent->wa_id,
            "👋 Welcome back, *{$agent->name}*!\nUse the buttons below to buy ZESA or update your settings.",
            [
                ['id' => 'buy_zesa', 'title' => '⚡ Buy ZESA'],
                ['id' => 'settings', 'title' => '⚙️ Settings'],
            ],
            'Magetsi Agents'
        );
    }

    /**
     * Handle a text message from the agent.
     */
    public function handleTextMessage(Agent $agent, string $text): void
    {
        $normalized = strtolower(trim($text));

        if (in_array($normalized, ['hi', 'hello', 'hey', 'start', 'menu'])) {
            $this->sendWelcome($agent);
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
        match ($buttonId) {
            'buy_zesa' => $this->launchBuyZesaFlow($agent),
            'settings' => $this->launchSettingsFlow($agent),
            default => $this->sendWelcome($agent),
        };
    }
}
