<?php

namespace App\Bot;

use App\Models\Agent;

class OnboardingFlow extends BotFlow
{
    public function name(): string
    {
        return 'onboarding';
    }

    public function priority(): int
    {
        return 100; // Highest priority — always checked first
    }

    public function shouldActivate(Agent $agent): bool
    {
        return $agent->needsOnboarding();
    }

    /**
     * Ignore greetings during onboarding — re-prompt instead.
     */
    public function isIgnoredInput(string $input): bool
    {
        return in_array(strtolower($input), ['hi', 'hello', 'hey', 'start', 'menu']);
    }

    public function steps(): array
    {
        return [
            'name' => [
                'message' => "👋 *Welcome to Magetsi Agents!*\n\n"
                    . "Before we get started, I need a few details.\n\n"
                    . "Please type your *first name*:",
                'validate' => fn (string $input) => (bool) preg_match('/^[a-zA-Z\s\-]{2,30}$/', $input),
                'error' => "❌ That doesn't look like a name. Please type your *first name* (letters only):",
                'transform' => fn (string $input) => ucfirst(strtolower(trim($input))),
                'save_as' => 'name',
                'next' => 'ecocash',
                'reply' => fn (Agent $agent, array $data) => "Nice to meet you, *{$data['name']}*! 😊\n\n"
                    . "Now, please type your *EcoCash number* (e.g. 0771234567):",
            ],
            'ecocash' => [
                'validate' => function (string $input) {
                    $digits = preg_replace('/\D/', '', $input);
                    return strlen($digits) >= 10 && strlen($digits) <= 12;
                },
                'error' => "❌ That doesn't look like a valid phone number.\n"
                    . "Please type your *EcoCash number* (e.g. 0771234567):",
                'transform' => function (string $input) {
                    $digits = preg_replace('/\D/', '', $input);
                    // Normalize to local format (0...)
                    if (str_starts_with($digits, '263') && strlen($digits) > 9) {
                        $digits = '0' . substr($digits, 3);
                    }
                    return $digits;
                },
                'save_as' => 'ecocash_number',
                'next' => null, // Terminal step
            ],
        ];
    }

    public function onComplete(Agent $agent, array $data, object $handler): void
    {
        $agent->completeOnboarding($data['name'], $data['ecocash_number']);

        $handler->whatsapp->sendTextMessage(
            $agent->wa_id,
            "✅ *You're all set, {$data['name']}!*\n\n"
            . "EcoCash: {$data['ecocash_number']}\n\n"
            . "You can change these anytime from ⚙️ Settings."
        );

        $handler->sendWelcome($agent, true);
    }
}
