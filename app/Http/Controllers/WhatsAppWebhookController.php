<?php

namespace App\Http\Controllers;

use App\Services\Conversation\ConversationHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    protected ConversationHandler $handler;

    public function __construct(ConversationHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Handle incoming webhook events (POST).
     */
    public function __invoke(Request $request): Response
    {
        $body = $request->all();

        Log::info('Webhook received', ['body' => $body]);

        // WhatsApp Cloud API sends notifications in this structure
        $entries = $body['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];

                foreach ($messages as $message) {
                    $this->processMessage($message, $contacts);
                }
            }
        }

        return response('OK', 200);
    }

    /**
     * Process a single inbound message.
     */
    protected function processMessage(array $message, array $contacts): void
    {
        $waId = $message['from'] ?? '';
        $type = $message['type'] ?? '';

        // Get contact name if available
        $contactName = null;
        foreach ($contacts as $contact) {
            if (($contact['wa_id'] ?? '') === $waId) {
                $contactName = $contact['profile']['name'] ?? null;
                break;
            }
        }

        $agent = $this->handler->resolveAgent($waId, $contactName);

        match ($type) {
            'text' => $this->handleText($agent, $message),
            'interactive' => $this->handleInteractive($agent, $message),
            default => Log::info("Unhandled message type: {$type}"),
        };
    }

    /**
     * Handle a plain text message.
     */
    protected function handleText($agent, array $message): void
    {
        $text = $message['text']['body'] ?? '';
        $this->handler->handleTextMessage($agent, $text);
    }

    /**
     * Handle interactive message replies (button clicks, list selections).
     */
    protected function handleInteractive($agent, array $message): void
    {
        $interactive = $message['interactive'] ?? [];
        $type = $interactive['type'] ?? '';

        match ($type) {
            'button_reply' => $this->handler->handleButtonReply(
                $agent,
                $interactive['button_reply']['id'] ?? ''
            ),
            'list_reply' => $this->handler->handleButtonReply(
                $agent,
                $interactive['list_reply']['id'] ?? ''
            ),
            'nfm_reply' => $this->handleFlowReply($agent, $interactive),
            default => Log::info("Unhandled interactive type: {$type}"),
        };
    }

    /**
     * Handle a completed WhatsApp Flow (nfm_reply).
     */
    protected function handleFlowReply($agent, array $interactive): void
    {
        $responseJson = $interactive['nfm_reply']['response_json'] ?? '{}';
        $data = json_decode($responseJson, true) ?? [];

        Log::info('Flow completed', ['agent' => $agent->id, 'data' => $data]);

        $trigger = $data['trigger'] ?? null;

        // Determine which flow based on the data keys present
        if ($trigger === 'buy_zesa') {
            $this->handler->handleZesaPurchase($agent, $data);
        } elseif ($trigger === 'save_settings') {
            $this->handler->handleSettingsUpdate($agent, $data);
        } else {
            Log::warning('Unknown flow response', ['data' => $data]);
            $this->handler->sendWelcome($agent);
        }
    }
}
