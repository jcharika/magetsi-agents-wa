<?php

namespace App\Services\Conversation;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed conversation session for tracking an agent's
 * position within a multi-step text flow.
 *
 * Sessions are keyed by WhatsApp ID and expire after 1 hour
 * of inactivity, encouraging impermanence of transient state.
 */
class ConversationSession
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    protected const TTL = 3600;

    /**
     * Cache key prefix.
     */
    protected const PREFIX = 'conversation_session:';

    public function __construct(
        public string $flow = 'idle',
        public string $step = '',
        public array $data = [],
    ) {}

    /**
     * Load a session from cache, or return a fresh idle session.
     */
    public static function load(string $waId): static
    {
        $cached = Cache::get(static::PREFIX . $waId);

        if ($cached && is_array($cached)) {
            return new static(
                flow: $cached['flow'] ?? 'idle',
                step: $cached['step'] ?? '',
                data: $cached['data'] ?? [],
            );
        }

        return new static();
    }

    /**
     * Persist the session to cache.
     */
    public function save(string $waId): void
    {
        Cache::put(static::PREFIX . $waId, [
            'flow' => $this->flow,
            'step' => $this->step,
            'data' => $this->data,
        ], static::TTL);
    }

    /**
     * Clear the session from cache (reset to idle).
     */
    public static function forget(string $waId): void
    {
        Cache::forget(static::PREFIX . $waId);
    }

    /**
     * Start a new flow at the given step.
     */
    public static function start(string $waId, string $flow, string $step): static
    {
        $session = new static(flow: $flow, step: $step);
        $session->save($waId);
        return $session;
    }

    /**
     * Advance to the next step, merging in collected data.
     */
    public function advance(string $waId, string $step, array $mergeData = []): static
    {
        $this->step = $step;
        $this->data = array_merge($this->data, $mergeData);
        $this->save($waId);
        return $this;
    }

    /**
     * Reset the session to idle state.
     */
    public function reset(string $waId): static
    {
        $this->flow = 'idle';
        $this->step = '';
        $this->data = [];
        static::forget($waId);
        return $this;
    }

    /**
     * Is the session in an active (non-idle) flow?
     */
    public function isActive(): bool
    {
        return $this->flow !== 'idle' && $this->step !== '';
    }

    /**
     * Get a piece of collected data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
