<?php

namespace Magetsi\Common\Services;

use Illuminate\Support\Facades\Log;

class MeterValidationService
{
    protected BackendManager $backend;

    public function __construct(BackendManager $backend)
    {
        $this->backend = $backend;
    }

    public function validate(string $meterNumber): array
    {
        Log::info('Validating meter', [
            'meter' => $meterNumber,
            'backend' => $this->backend->getBackendName(),
        ]);

        return $this->backend->validateMeter($meterNumber);
    }
}
