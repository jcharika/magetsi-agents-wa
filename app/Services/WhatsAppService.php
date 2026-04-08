<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $token;
    protected string $phoneNumberId;

    public function __construct()
    {
        $this->apiUrl = config('whatsapp.api_url');
        $this->token = config('whatsapp.token');
        $this->phoneNumberId = config('whatsapp.phone_number_id');
    }

    /**
     * Send a simple text message.
     */
    public function sendTextMessage(string $to, string $text): array
    {
        return $this->sendMessage($to, [
            'type' => 'text',
            'text' => ['body' => $text],
        ]);
    }

    /**
     * Send interactive reply buttons (max 3 buttons).
     */
    public function sendInteractiveButtons(string $to, string $body, array $buttons, ?string $header = null, ?string $footer = null): array
    {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $body],
            'action' => [
                'buttons' => collect($buttons)->map(fn ($btn, $i) => [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $btn['id'],
                        'title' => $btn['title'],
                    ],
                ])->values()->toArray(),
            ],
        ];

        if ($header) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        return $this->sendMessage($to, [
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    /**
     * Send an interactive list message.
     */
    public function sendInteractiveList(string $to, string $body, string $buttonText, array $sections, ?string $header = null, ?string $footer = null): array
    {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $body],
            'action' => [
                'button' => $buttonText,
                'sections' => $sections,
            ],
        ];

        if ($header) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }

        return $this->sendMessage($to, [
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    /**
     * Send a WhatsApp Flow message (interactive CTA).
     *
     * If $screenId is null, uses flow_action=data_exchange so WhatsApp
     * fetches screen data from the configured endpoint.
     * If $screenId is set, uses flow_action=navigate with inline data.
     */
    public function sendFlow(string $to, string $flowId, string $flowToken, ?string $screenId = null, ?array $data = null, string $ctaText = 'Open', $message = 'Tap the button below to continue.'): array
    {
        $flowAction = [
            'name' => 'flow',
            'parameters' => [
                'flow_message_version' => '3',
                'flow_id' => $flowId,
                'flow_token' => $flowToken,
                'mode' => 'published',
                'flow_cta' => $ctaText,
            ],
        ];

        if ($screenId) {
            // Navigate mode — inline data, no endpoint call
            $flowAction['parameters']['flow_action'] = 'navigate';
            $flowAction['parameters']['flow_action_payload'] = [
                'screen' => $screenId,
            ];

            if ($data) {
                $flowAction['parameters']['flow_action_payload']['data'] = $data;
            }
        } else {
            // Data exchange mode — endpoint provides screen data
            $flowAction['parameters']['flow_action'] = 'data_exchange';
        }

        return $this->sendMessage($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'body' => [
                    'text' => $message
                ],
                'action' => $flowAction,
            ],
        ]);
    }

    /**
     * Send a message template with a FLOW button.
     *
     * Use this for business-initiated conversations (outside 24h window).
     * The template must be pre-approved in Meta Business Suite with a
     * FLOW button type attached.
     *
     * @param string      $to           Recipient WhatsApp ID
     * @param string      $templateName Approved template name (e.g. "buy_zesa_flow")
     * @param string      $language     Template language code (e.g. "en")
     * @param string|null $flowToken    Flow token for session tracking
     * @param array|null  $flowData     Initial data payload for the first screen
     * @param array       $bodyParams   Template body parameter substitutions
     */
    public function sendFlowTemplate(
        string $to,
        string $templateName,
        string $language = 'en',
        ?string $flowToken = null,
        ?array $flowData = null,
        array $bodyParams = [],
    ): array {
        $components = [];

        // Body parameters (template variable substitutions like {{1}})
        if (! empty($bodyParams)) {
            $components[] = [
                'type' => 'body',
                'parameters' => collect($bodyParams)->map(fn ($val) => [
                    'type' => 'text',
                    'text' => (string) $val,
                ])->values()->toArray(),
            ];
        }

        // Flow button component (always index 0 — the first button)
        $flowComponent = [
            'type' => 'button',
            'sub_type' => 'flow',
            'index' => '0',
            'parameters' => [
                [
                    'type' => 'action',
                    'action' => array_filter([
                        'flow_token' => $flowToken ?? 'unused',
                        'flow_action_data' => $flowData,
                    ]),
                ],
            ],
        ];
        $components[] = $flowComponent;

        return $this->sendMessage($to, [
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $language,
                ],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Low-level send message via WhatsApp Cloud API.
     */
    protected function sendMessage(string $to, array $messageData): array
    {
        $payload = array_merge([
            'messaging_product' => 'whatsapp',
            'to' => $to,
        ], $messageData);

        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        Log::info('WhatsApp API request', ['url' => $url, 'payload' => $payload]);

        $response = Http::withToken($this->token)
            ->post($url, $payload);

        $result = $response->json();

        Log::info('WhatsApp API response', ['status' => $response->status(), 'body' => $result]);

        if ($response->failed()) {
            Log::error('WhatsApp API error', ['status' => $response->status(), 'body' => $result]);
        }

        return $result ?? [];
    }
}
