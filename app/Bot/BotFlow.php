<?php

namespace App\Bot;

use App\Models\Agent;

/**
 * Base class for conversational bot flows.
 *
 * Each flow defines a sequence of steps that collect text input from the agent.
 * Steps are processed by the FlowEngine, which handles validation, state
 * persistence, and message delivery.
 *
 * To create a new flow, extend this class in app/Bot/ and it will be
 * auto-discovered by the FlowEngine.
 */
abstract class BotFlow
{
    /**
     * Unique flow identifier (e.g., 'onboarding', 'support_ticket').
     */
    abstract public function name(): string;

    /**
     * Define the ordered steps for this flow.
     *
     * Each step is an array with these keys:
     *
     *   'message'   (string|Closure)  — Prompt sent to start this step.
     *                                    Closure receives (Agent $agent, array $sessionData).
     *   'validate'  (Closure)         — fn(string $input): bool. Return true if input is valid.
     *   'error'     (string|Closure)  — Message sent when validation fails.
     *   'transform' (Closure|null)    — fn(string $input): mixed. Transform before saving.
     *   'save_as'   (string)          — Key to store the value in session data.
     *   'next'      (string|null)     — Next step name, or null if this is the last step.
     *   'reply'     (string|Closure|null) — Message sent after valid input (before next step prompt).
     *                                       Closure receives (Agent $agent, array $sessionData).
     *
     * @return array<string, array> Keyed by step name.
     */
    abstract public function steps(): array;

    /**
     * Called when the final step completes successfully.
     *
     * Use this to persist collected data, update the agent, and send
     * a completion message.
     *
     * @param Agent $agent       The agent who completed the flow.
     * @param array $data        All collected data from the flow steps.
     * @param object $handler    The ConversationHandler (for sending messages).
     */
    abstract public function onComplete(Agent $agent, array $data, object $handler): void;

    /**
     * Whether this flow should activate for the given agent.
     *
     * Override to control when a flow should start automatically.
     * For example, onboarding starts when agent->needsOnboarding().
     *
     * @return bool True if the flow should start for this agent.
     */
    public function shouldActivate(Agent $agent): bool
    {
        return false;
    }

    /**
     * Priority for auto-activation (higher = checked first).
     */
    public function priority(): int
    {
        return 0;
    }

    /**
     * Get the first step name.
     */
    public function firstStep(): string
    {
        return array_key_first($this->steps());
    }

    /**
     * Check if a given input should be ignored during this flow
     * (e.g., greeting keywords that shouldn't be treated as step input).
     */
    public function isIgnoredInput(string $input): bool
    {
        return false;
    }
}
