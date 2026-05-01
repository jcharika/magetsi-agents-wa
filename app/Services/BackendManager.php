<?php

namespace App\Services;

use App\Contracts\TransactionBackend;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Factory that resolves the active transaction backend.
 *
 * Switch between backends via MAGETSI_BACKEND env var:
 *  - "new"    → MagetsiApiService (magetsi.test, 4-step API)
 *  - "legacy" → LegacyMagetsiService (magetsi.co.zw, 2-step web)
 *  - "mock"   → MockMagetsiService (testing - always succeeds)
 */
class BackendManager
{
    protected ?TransactionBackend $resolved = null;

    /**
     * Get the active transaction backend.
     */
    public function driver(): TransactionBackend
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        // Check mock state first (runtime toggle)
        if (MockState::isEnabled()) {
            Log::debug('[BackendManager] Using mock backend (runtime toggle)');
            $this->resolved = app(MockMagetsiService::class);
            return $this->resolved;
        }

        $backend = config('magetsi.backend', 'new');

        Log::debug('[BackendManager] Resolving backend', ['backend' => $backend]);

        $this->resolved = match ($backend) {
            'new', 'api' => app(MagetsiApiService::class),
            'legacy', 'website' => app(LegacyMagetsiService::class),
            'mock', 'testing' => app(MockMagetsiService::class),
            default => throw new InvalidArgumentException("Unknown backend: {$backend}"),
        };

        return $this->resolved;
    }

    /**
     * Get the backend name for display/logging.
     */
    public function getBackendName(): string
    {
        return $this->driver()->getBackendName();
    }

    /**
     * Convenience: validate meter via active backend.
     */
    public function validateMeter(string $meterNumber): array
    {
        return $this->driver()->validateMeter($meterNumber);
    }

    /**
     * Convenience: process transaction via active backend.
     */
    public function processTransaction(array $params): array
    {
        return $this->driver()->processTransaction($params);
    }

    /**
     * Reset the resolved backend (useful for testing).
     */
    public function reset(): void
    {
        $this->resolved = null;
    }
}
