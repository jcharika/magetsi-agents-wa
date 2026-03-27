<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MeterValidationService
{
    /**
     * Validate a ZESA meter number.
     *
     * In production, this would call the ZESA/ZETDC API.
     * For now, returns simulated results.
     */
    public function validate(string $meterNumber): array
    {
        Log::info('Validating meter', ['meter' => $meterNumber]);

        // Simulated validation — any 11‑digit number except all-zeros is "valid"
        $digits = preg_replace('/\D/', '', $meterNumber);

        if (strlen($digits) !== 11) {
            return [
                'valid' => false,
                'error' => 'Meter number must be exactly 11 digits.',
            ];
        }

        if ($digits === '00000000000') {
            return [
                'valid' => false,
                'error' => 'Meter not found. Check number and try again.',
            ];
        }

        // Simulated lookup
        return [
            'valid' => true,
            'name' => 'Farai Moyo',
            'address' => '23 Rotten Row, Harare',
            'meter_number' => $digits,
        ];
    }
}
