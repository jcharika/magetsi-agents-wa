<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Validates ZESA meter numbers via the active backend.
 *
 * Delegates to BackendManager which routes to either:
 *  - MagetsiApiService (new API: prepare → validate)
 *  - LegacyMagetsiService (legacy: /bills/zesaMeter/check)
 */
class MeterValidationService
{
    protected BackendManager $backend;

    public function __construct(BackendManager $backend)
    {
        $this->backend = $backend;
    }

    /**
     * Validate a ZESA meter number.
     *
     * Returns a normalized array regardless of which backend is active.
     */
    public function validate(string $meterNumber): array
    {
        Log::info('Validating meter', [
            'meter' => $meterNumber,
            'backend' => $this->backend->getBackendName(),
        ]);

        return $this->backend->validateMeter($meterNumber);
    }
}
