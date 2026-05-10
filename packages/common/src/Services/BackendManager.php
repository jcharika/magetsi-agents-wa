<?php

namespace Magetsi\Common\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Magetsi\Common\Contracts\TransactionBackend;

class BackendManager
{
    protected ?TransactionBackend $resolved = null;

    public function driver(): TransactionBackend
    {
        if ($this->resolved) {
            return $this->resolved;
        }

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

    public function getBackendName(): string
    {
        return $this->driver()->getBackendName();
    }

    public function validateMeter(string $meterNumber): array
    {
        return $this->driver()->validateMeter($meterNumber);
    }

    public function processTransaction(array $params): array
    {
        return $this->driver()->processTransaction($params);
    }

    public function reset(): void
    {
        $this->resolved = null;
    }
}
