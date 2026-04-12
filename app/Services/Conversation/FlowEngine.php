<?php

namespace App\Services\Conversation;

use App\Bot\BotFlow;
use App\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Processes conversation flow steps and auto-discovers BotFlow classes.
 *
 * All classes in app/Bot/ that extend BotFlow are automatically registered.
 * The engine matches incoming text input against the active session step,
 * validates, transforms, and advances the session.
 */
class FlowEngine
{
    /**
     * @var Collection<string, BotFlow> Registered flows keyed by name.
     */
    protected Collection $flows;

    public function __construct()
    {
        $this->flows = $this->discoverFlows();
    }

    // ── Auto-discovery ──────────────────────────────

    /**
     * Scan app/Bot/ for BotFlow subclasses and instantiate them.
     */
    protected function discoverFlows(): Collection
    {
        $flows = collect();
        $botDir = app_path('Bot');

        if (! File::isDirectory($botDir)) {
            return $flows;
        }

        foreach (File::files($botDir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = 'App\\Bot\\' . $file->getFilenameWithoutExtension();

            if (! class_exists($className)) {
                continue;
            }

            // Skip the abstract base class
            $reflection = new \ReflectionClass($className);
            if ($reflection->isAbstract()) {
                continue;
            }

            if (is_subclass_of($className, BotFlow::class)) {
                $instance = app($className);
                $flows->put($instance->name(), $instance);
            }
        }

        // Sort by priority (highest first)
        return $flows->sortByDesc(fn (BotFlow $flow) => $flow->priority());
    }

    // ── Public API ──────────────────────────────────

    /**
     * Get a registered flow by name.
     */
    public function getFlow(string $name): ?BotFlow
    {
        return $this->flows->get($name);
    }

    /**
     * Get all registered flows.
     */
    public function all(): Collection
    {
        return $this->flows;
    }

    /**
     * Check if any flow should auto-activate for this agent.
     * Returns the flow that should start, or null.
     */
    public function findActivatableFlow(Agent $agent): ?BotFlow
    {
        foreach ($this->flows as $flow) {
            if ($flow->shouldActivate($agent)) {
                return $flow;
            }
        }

        return null;
    }

    /**
     * Start a flow for the given agent.
     * Sends the first step's prompt message.
     *
     * @return ConversationSession The newly created session.
     */
    public function startFlow(Agent $agent, BotFlow $flow, object $handler): ConversationSession
    {
        $firstStep = $flow->firstStep();
        $session = ConversationSession::start($agent->wa_id, $flow->name(), $firstStep);

        $stepConfig = $flow->steps()[$firstStep] ?? null;
        if ($stepConfig && isset($stepConfig['message'])) {
            $message = $this->resolveValue($stepConfig['message'], $agent, $session->data);
            $handler->whatsapp->sendTextMessage($agent->wa_id, $message);
        }

        Log::info('Flow started', [
            'agent' => $agent->id,
            'flow' => $flow->name(),
            'step' => $firstStep,
        ]);

        return $session;
    }

    /**
     * Process text input against the current session step.
     *
     * Returns true if the input was handled by the flow engine.
     * Returns false if the session is idle (caller should handle normally).
     */
    public function processInput(Agent $agent, ConversationSession $session, string $text, object $handler): bool
    {
        if (! $session->isActive()) {
            return false;
        }

        $flow = $this->getFlow($session->flow);
        if (! $flow) {
            Log::warning('Session references unknown flow', [
                'flow' => $session->flow,
                'agent' => $agent->id,
            ]);
            $session->reset($agent->wa_id);
            return false;
        }

        // Check if this input should be ignored (e.g., greetings during onboarding)
        if ($flow->isIgnoredInput($text)) {
            $stepConfig = $flow->steps()[$session->step] ?? null;
            if ($stepConfig) {
                // Re-send a gentle re-prompt
                $error = $stepConfig['error'] ?? null;
                $message = $stepConfig['message'] ?? null;
                $prompt = $this->resolveValue($message, $agent, $session->data);
                $handler->whatsapp->sendTextMessage(
                    $agent->wa_id,
                    "Hi there, " . ($prompt ? lcfirst(strip_tags($prompt)) : "please continue with the current step.")
                );
            }
            return true;
        }

        $steps = $flow->steps();
        $stepConfig = $steps[$session->step] ?? null;

        if (! $stepConfig) {
            Log::warning('Session references unknown step', [
                'flow' => $session->flow,
                'step' => $session->step,
            ]);
            $session->reset($agent->wa_id);
            return false;
        }

        // Validate input
        $validator = $stepConfig['validate'] ?? null;
        if ($validator && ! $validator($text)) {
            $error = $this->resolveValue($stepConfig['error'] ?? "Invalid input. Please try again.", $agent, $session->data);
            $handler->whatsapp->sendTextMessage($agent->wa_id, $error);
            return true;
        }

        // Transform input
        $transformer = $stepConfig['transform'] ?? null;
        $value = $transformer ? $transformer($text) : $text;

        // Save to session data
        $saveAs = $stepConfig['save_as'] ?? null;
        $mergeData = $saveAs ? [$saveAs => $value] : [];
        $sessionData = array_merge($session->data, $mergeData);

        // Send reply (acknowledgement before next step prompt)
        $reply = $stepConfig['reply'] ?? null;
        if ($reply) {
            $replyText = $this->resolveValue($reply, $agent, $sessionData);
            $handler->whatsapp->sendTextMessage($agent->wa_id, $replyText);
        }

        // Determine next step
        $nextStep = $stepConfig['next'] ?? null;

        if ($nextStep && isset($steps[$nextStep])) {
            // Advance to next step
            $session->advance($agent->wa_id, $nextStep, $mergeData);

            // Send next step's prompt
            $nextConfig = $steps[$nextStep];
            if (isset($nextConfig['message'])) {
                $message = $this->resolveValue($nextConfig['message'], $agent, $session->data);
                $handler->whatsapp->sendTextMessage($agent->wa_id, $message);
            }

            Log::info('Flow step advanced', [
                'agent' => $agent->id,
                'flow' => $session->flow,
                'step' => $nextStep,
            ]);
        } else {
            // Terminal step — complete the flow
            $session->reset($agent->wa_id);

            Log::info('Flow completed', [
                'agent' => $agent->id,
                'flow' => $flow->name(),
                'data' => $sessionData,
            ]);

            $flow->onComplete($agent, $sessionData, $handler);
        }

        return true;
    }

    /**
     * Get the current step config for a session (for simulator metadata).
     */
    public function getStepConfig(ConversationSession $session): ?array
    {
        if (! $session->isActive()) {
            return null;
        }

        $flow = $this->getFlow($session->flow);
        if (! $flow) {
            return null;
        }

        return $flow->steps()[$session->step] ?? null;
    }

    // ── Helpers ─────────────────────────────────────

    /**
     * Resolve a value that may be a string or a Closure.
     */
    protected function resolveValue(mixed $value, Agent $agent, array $data): string
    {
        if ($value instanceof \Closure) {
            return $value($agent, $data);
        }

        return (string) $value;
    }
}
