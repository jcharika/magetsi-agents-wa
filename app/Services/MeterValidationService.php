<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Validates ZESA meter numbers via the Magetsi backend API.
 *
 * Uses the prepare → validate flow:
 * 1. Prepare to get a trace ID
 * 2. Validate with the meter number as biller_account
 *
 * The validation result includes the customer name, address, and currency
 * which the flow needs to show the user before submitting.
 */
class MeterValidationService
{
    protected MagetsiApiService $api;

    public function __construct(MagetsiApiService $api)
    {
        $this->api = $api;
    }

    /**
     * Validate a ZESA meter number.
     *
     * Returns:
     *  - valid: bool
     *  - name: customer name (if valid)
     *  - address: customer address (if valid)
     *  - meter_number: the validated meter number
     *  - currency: the meter's currency (e.g. USD, ZWG)
     *  - trace: the trace ID for subsequent steps
     *  - debit: available payment methods for this meter
     *  - error: error message (if invalid)
     */
    public function validate(string $meterNumber): array
    {
        Log::info('Validating meter via Magetsi API', ['meter' => $meterNumber]);

        $digits = preg_replace('/\D/', '', $meterNumber);

        if (strlen($digits) !== 11) {
            return [
                'valid' => false,
                'error' => 'Meter number must be exactly 11 digits.',
            ];
        }

        // Step 1: Prepare to get a trace ID
        $prepare = $this->api->prepare('ZESA');

        if (! $prepare['success']) {
            Log::error('Prepare failed during meter validation', $prepare);
            return [
                'valid' => false,
                'error' => $prepare['error'] ?? 'Service temporarily unavailable. Please try again.',
            ];
        }

        $trace = $prepare['trace'];

        // Step 2: Validate the meter
        $validation = $this->api->validate('ZESA', $trace, $digits);

        if (! $validation['success']) {
            return [
                'valid' => false,
                'error' => $validation['error'] ?? 'Meter not found. Check number and try again.',
            ];
        }

        return [
            'valid' => true,
            'name' => $validation['recipient_name'],
            'address' => $validation['recipient_address'],
            'meter_number' => $validation['biller_account'],
            'currency' => $validation['currency'],
            'recipient_currency' => $validation['recipient_currency'],
            'trace' => $trace,
            'debit' => $validation['debit'],
        ];
    }
}
