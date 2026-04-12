<?php

namespace App\Services\Conversation;

/**
 * Captures messages instead of sending them via WhatsApp.
 *
 * Used by the simulator to collect messages that would be sent
 * by the FlowEngine, then return them as a JSON response.
 */
class SimulatorMessageCollector
{
    public array $messages = [];

    /**
     * Capture a text message.
     */
    public function sendTextMessage(string $to, string $text): array
    {
        $this->messages[] = ['type' => 'text', 'text' => $text];
        return [];
    }

    /**
     * Capture an interactive button message.
     */
    public function sendInteractiveButtons(string $to, string $text, array $buttons, ?string $header = null): array
    {
        $this->messages[] = [
            'type' => 'buttons',
            'text' => $text,
            'header' => $header,
            'buttons' => $buttons,
        ];
        return [];
    }

    /**
     * Capture a flow message.
     */
    public function sendFlow(string $to, string $flowId, string $flowToken, ?string $screenId = null, ?array $data = null, string $ctaText = 'Open', $message = ''): array
    {
        $this->messages[] = [
            'type' => 'flow',
            'flow_id' => $flowId,
            'cta' => $ctaText,
            'text' => $message,
        ];
        return [];
    }

    /**
     * Capture a flow template message (renders the same as a flow CTA in sim).
     */
    public function sendFlowTemplate(string $to, string $templateName, string $language = 'en', ?string $flowToken = null, ?array $flowData = null, array $bodyParams = []): array
    {
        $this->messages[] = [
            'type' => 'flow',
            'flow_id' => $templateName,
            'cta' => $templateName,
            'text' => 'Flow template: ' . $templateName,
        ];
        return [];
    }

    /**
     * Get all collected messages and reset.
     */
    public function flush(): array
    {
        $messages = $this->messages;
        $this->messages = [];
        return $messages;
    }
}
