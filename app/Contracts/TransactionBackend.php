<?php

namespace App\Contracts;

/**
 * Contract for Magetsi transaction backends.
 *
 * Both the new API (magetsi.test) and the legacy website (magetsi.co.zw)
 * must implement this interface so they can be swapped via config.
 */
interface TransactionBackend
{
    /**
     * Validate a meter number.
     *
     * Must return a normalized array:
     *  - valid: bool
     *  - name: customer name (if valid)
     *  - address: customer address (if valid)
     *  - meter_number: validated meter number
     *  - currency: meter's currency (e.g. USD, ZWG)
     *  - trace: backend session/trace ID (may be empty for legacy)
     *  - debit: available payment methods (may be empty for legacy)
     *  - error: error message (if invalid)
     */
    public function validateMeter(string $meterNumber): array;

    /**
     * Process a ZESA transaction end-to-end.
     *
     * Must return a normalized array:
     *  - success: bool
     *  - transaction: array with keys: status, uid, customer_reference, payment_amount, etc.
     *  - confirmation: array with fee breakdown (amounts, etc.)
     *  - error: error message (if failed)
     *  - raw_response: full backend response for storage
     */
    public function processTransaction(array $params): array;

    /**
     * Get the backend identifier.
     */
    public function getBackendName(): string;
}
