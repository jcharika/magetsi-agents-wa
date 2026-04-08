<?php

namespace App\Services;

use App\Contracts\TransactionBackend;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Legacy backend — connects to magetsi.co.zw API v1.
 *
 * Endpoints:
 *  - Check meter:       POST /api/zesa/v1/meters/check
 *  - Init purchase:     POST /api/zesa/v1/init
 *  - Check transaction: POST /api/zesa/v1/transactions/check
 *
 * All requests require `_token` for authentication.
 * This is a simpler 2-step flow (validate → init) compared to the
 * new API's 4-step pipeline (prepare → validate → confirm → process).
 * After init, EcoCash payments are async — poll status with transactions/check.
 */
class LegacyMagetsiService implements TransactionBackend
{
    protected string $baseUrl;
    protected string $apiToken;
    protected int $timeout;
    protected int $pollAttempts;
    protected int $pollIntervalMs;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('magetsi.legacy_url', 'https://magetsi.co.zw'), '/');
        $this->apiToken = config('magetsi.legacy_token', '');
        $this->timeout = config('magetsi.timeout', 30);
        $this->pollAttempts = config('magetsi.legacy_poll_attempts', 10);
        $this->pollIntervalMs = config('magetsi.legacy_poll_interval', 3000);
    }

    public function getBackendName(): string
    {
        return 'legacy';
    }

    // ── TransactionBackend interface ─────────────────

    /**
     * Validate a meter number.
     *
     * POST /api/zesa/v1/meters/check
     * { _token: "...", meter: "12345678901" }
     *
     * Success: { success: true, body: { premium, limitZWG, limitUSD, meter: { success, name, address } } }
     * Error:   HTTP 422 { errors: { meter: ["..."] } }
     */
    public function validateMeter(string $meterNumber): array
    {
        Log::info('[LegacyBackend] Validating meter', ['meter' => $meterNumber]);

        $digits = preg_replace('/\D/', '', $meterNumber);

        if (strlen($digits) !== 11) {
            return ['valid' => false, 'error' => 'Meter number must be exactly 11 digits.'];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->baseUrl}/api/zesa/v1/meters/check", [
                    '_token' => $this->apiToken,
                    'meter' => $digits,
                ]);

            $json = $response->json() ?? [];

            Log::info('[LegacyBackend] Meter check response', [
                'status' => $response->status(),
                'body' => $json,
            ]);

            // HTTP 422 — validation error
            if ($response->status() === 422) {
                $errors = $json['errors'] ?? [];
                $errorMsg = $this->flattenErrors($errors) ?: ($json['message'] ?? 'Meter validation failed.');
                return ['valid' => false, 'error' => $errorMsg];
            }

            // API-level failure
            if (! ($json['success'] ?? false)) {
                return [
                    'valid' => false,
                    'error' => $json['message'] ?? 'Meter validation failed.',
                ];
            }

            $body = $json['body'] ?? [];
            $meter = $body['meter'] ?? [];

            // Meter lookup failed inside the API
            if (! ($meter['success'] ?? false)) {
                return [
                    'valid' => false,
                    'error' => 'Meter number not found. Please check and try again.',
                ];
            }

            return [
                'valid' => true,
                'name' => $meter['name'] ?? '',
                'address' => $meter['address'] ?? '',
                'meter_number' => $digits,
                'currency' => 'ZWG', // default; the meter check doesn't return currency
                'recipient_currency' => 'ZWG',
                'trace' => null,
                'debit' => [],
                // Extra legacy-specific data for transaction processing
                'legacy_meta' => [
                    'premium' => $body['premium'] ?? null,
                    'limitZWG' => $body['limitZWG'] ?? null,
                    'limitUSD' => $body['limitUSD'] ?? null,
                    'disableUsdForZwgMeters' => $body['disableUsdForZwgMeters'] ?? false,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('[LegacyBackend] Meter validation error', ['error' => $e->getMessage()]);
            return ['valid' => false, 'error' => 'Connection error. Please try again.'];
        }
    }

    /**
     * Process a ZESA transaction.
     *
     * POST /api/zesa/v1/init
     * { _token, meter, payment, phone, meter_currency, amount, email }
     *
     * After init, polls /api/zesa/v1/transactions/check until complete.
     */
    public function processTransaction(array $params): array
    {
        Log::info('[LegacyBackend] Processing transaction', $params);

        $meterNumber = $params['meter_number'];
        $amount = (float) $params['amount'];
        $currency = strtoupper($params['currency'] ?? 'ZWG');
        $ecocashNumber = $params['ecocash_number'];
        $email = $params['email'] ?? config('magetsi.legacy_email', 'agent@magetsi.co.zw');

        // Format phone number
        $formattedPhone = $this->formatPhoneNumber($ecocashNumber);

        // Resolve payment method
        $paymentMethod = $this->resolvePaymentMethod($currency);

        $payload = [
            '_token' => $this->apiToken,
            'meter' => $meterNumber,
            'payment' => $paymentMethod,
            'phone' => $formattedPhone,
            'meter_currency' => $currency,
            'amount' => $amount,
            'email' => $email,
        ];

        try {
            // ── Step 1: Init ──
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->baseUrl}/api/zesa/v1/init", $payload);

            $json = $response->json() ?? [];

            Log::info('[LegacyBackend] Init response', [
                'status' => $response->status(),
                'body' => $json,
            ]);

            // HTTP 422 — validation errors
            if ($response->status() === 422) {
                $errors = $json['errors'] ?? [];
                $errorMsg = $this->flattenErrors($errors) ?: ($json['message'] ?? 'Transaction validation failed.');
                return ['success' => false, 'error' => $errorMsg, 'raw_response' => $json];
            }

            // API-level failure
            if (! ($json['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => $json['message'] ?? 'Transaction initiation failed.',
                    'raw_response' => $json,
                ];
            }

            $body = $json['body'] ?? [];
            $payment = $body['payment'] ?? [];
            $ref = $payment['ref'] ?? null;
            $paymentType = $payment['payment'] ?? $paymentMethod;

            // Stripe: return redirect URL
            if ($paymentType === 'stripe' && isset($payment['url'])) {
                return [
                    'success' => true,
                    'transaction' => [
                        'status' => 'REDIRECT',
                        'uid' => $ref,
                        'external_uid' => $ref,
                        'customer_reference' => $ref,
                        'payment_amount' => $amount,
                        'biller_status' => null,
                        'payment_status' => 'REDIRECT',
                        'reference' => $ref,
                        'redirect_url' => $payment['url'],
                    ],
                    'confirmation' => [],
                    'raw_response' => $json,
                ];
            }

            // ── Step 2: Poll for completion (EcoCash) ──
            if ($ref) {
                $pollResult = $this->pollTransactionStatus($ref);

                return [
                    'success' => true,
                    'transaction' => [
                        'status' => $pollResult['completed'] ? 'COMPLETED' : 'PENDING',
                        'uid' => $ref,
                        'external_uid' => $ref,
                        'customer_reference' => $ref,
                        'payment_amount' => $amount,
                        'biller_status' => $pollResult['completed'] ? 'COMPLETED' : 'PENDING',
                        'payment_status' => $pollResult['completed'] ? 'COMPLETED' : 'PENDING',
                        'reference' => $ref,
                    ],
                    'confirmation' => [],
                    'raw_response' => array_merge($json, ['poll_result' => $pollResult]),
                ];
            }

            // No ref returned — treat as success with unknown status
            return [
                'success' => true,
                'transaction' => [
                    'status' => 'PENDING',
                    'uid' => null,
                    'external_uid' => null,
                    'customer_reference' => null,
                    'payment_amount' => $amount,
                    'biller_status' => null,
                    'payment_status' => 'PENDING',
                    'reference' => null,
                ],
                'confirmation' => [],
                'raw_response' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('[LegacyBackend] Transaction error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    // ── Transaction Status Polling ──────────────────

    /**
     * Poll transaction status until complete or max attempts reached.
     *
     * POST /api/zesa/v1/transactions/check
     * { _token, ref }
     *
     * Returns: { pending: true/false }
     */
    protected function pollTransactionStatus(string $ref): array
    {
        Log::info('[LegacyBackend] Polling transaction status', ['ref' => $ref]);

        for ($i = 0; $i < $this->pollAttempts; $i++) {
            // Wait between polls (skip the first wait)
            if ($i > 0) {
                usleep($this->pollIntervalMs * 1000);
            }

            try {
                $response = Http::timeout($this->timeout)
                    ->acceptJson()
                    ->post("{$this->baseUrl}/api/zesa/v1/transactions/check", [
                        '_token' => $this->apiToken,
                        'ref' => $ref,
                    ]);

                $json = $response->json() ?? [];

                Log::info('[LegacyBackend] Poll response', [
                    'attempt' => $i + 1,
                    'body' => $json,
                ]);

                // Failed transaction
                if (! ($json['success'] ?? true)) {
                    return [
                        'completed' => false,
                        'failed' => true,
                        'message' => $json['message'] ?? 'Transaction failed.',
                        'attempts' => $i + 1,
                    ];
                }

                $body = $json['body'] ?? [];
                $pending = $body['pending'] ?? true;

                // Transaction completed
                if (! $pending) {
                    return [
                        'completed' => true,
                        'failed' => false,
                        'message' => 'Transaction completed.',
                        'attempts' => $i + 1,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[LegacyBackend] Poll error', [
                    'attempt' => $i + 1,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Max attempts reached — still pending
        Log::warning('[LegacyBackend] Poll max attempts reached', ['ref' => $ref]);
        return [
            'completed' => false,
            'failed' => false,
            'message' => 'Transaction still processing. Check again shortly.',
            'attempts' => $this->pollAttempts,
        ];
    }

    // ── Helpers ──────────────────────────────────────

    /**
     * Format a phone number to 0... local format.
     *
     * The legacy API accepts local format (0771234567).
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // If starts with 263, convert to local
        if (str_starts_with($digits, '263') && strlen($digits) > 9) {
            $digits = '0' . substr($digits, 3);
        }

        // If doesn't start with 0, prepend it
        if (! str_starts_with($digits, '0') && strlen($digits) <= 9) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * Resolve the payment method string.
     *
     * Legacy API accepts: "ecocash", "ecocash-usd", "stripe"
     */
    protected function resolvePaymentMethod(string $currency): string
    {
        $currency = strtolower($currency);

        return match ($currency) {
            'usd' => 'ecocash-usd',
            'zwg', 'zwl', 'zig' => 'ecocash',
            default => 'ecocash',
        };
    }

    /**
     * Flatten Laravel validation errors into a single string.
     *
     * { "meter": ["error1"], "amount": ["error2"] } → "error1. error2."
     */
    protected function flattenErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $field => $fieldErrors) {
            foreach ((array) $fieldErrors as $msg) {
                $messages[] = $msg;
            }
        }
        return implode(' ', $messages);
    }
}
