<?php

namespace Magetsi\Common\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Magetsi\Common\Contracts\TransactionBackend;

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
        $this->pollIntervalMs = config('magetsi.legacy_poll_interval', 1000);
    }

    public function getBackendName(): string
    {
        return 'legacy';
    }

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

            if ($response->status() === 422) {
                $errors = $json['errors'] ?? [];
                $errorMsg = $this->flattenErrors($errors) ?: ($json['message'] ?? 'Meter validation failed.');
                return ['valid' => false, 'error' => $errorMsg];
            }

            if (! ($json['success'] ?? false)) {
                return [
                    'valid' => false,
                    'error' => $json['message'] ?? 'Meter validation failed.',
                ];
            }

            $body = $json['body'] ?? [];
            $meter = $body['meter'] ?? [];

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
                'currency' => 'ZWG',
                'recipient_currency' => 'ZWG',
                'trace' => null,
                'debit' => [],
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

    public function processTransaction(array $params): array
    {
        Log::info('[LegacyBackend] Processing transaction', $params);

        $type = $params['type'] ?? 'zesa';

        return match ($type) {
            'zesa' => $this->processZesaTransaction($params),
            'airtime' => $this->processAirtime($params),
            'bundle' => $this->processBundle($params),
            'telone' => $this->processTelone($params),
            'biller' => $this->processBillerPayment($params),
            default => $this->processZesaTransaction($params),
        };
    }

    protected function processZesaTransaction(array $params): array
    {
        Log::info('[LegacyBackend] Processing ZESA transaction', $params);

        $meterNumber = $params['meter_number'];
        $amount = (float) $params['amount'];
        $currency = strtoupper($params['currency'] ?? 'ZWG');
        $ecocashNumber = $params['ecocash_number'];
        $customerPhone = $params['recipient_phone'] ?? $ecocashNumber;
        $email = $params['email'] ?? config('magetsi.legacy_email', 'agent@magetsi.co.zw');

        $formattedPhone = $this->formatPhoneNumber($ecocashNumber);
        $paymentMethod = $this->resolvePaymentMethod($currency);

        $payload = [
            '_token' => $this->apiToken,
            'meter' => $meterNumber,
            'payment' => $paymentMethod,
            'phone' => $formattedPhone,
            'notification_phone' => $customerPhone,
            'meter_currency' => $currency,
            'amount' => $amount,
            'email' => $email,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->baseUrl}/api/zesa/v1/init", $payload);

            $json = $response->json() ?? [];

            Log::info('[LegacyBackend] Init response', [
                'status' => $response->status(),
                'body' => $json,
            ]);

            if ($response->status() === 422) {
                $errors = $json['errors'] ?? [];
                $errorMsg = $this->flattenErrors($errors) ?: ($json['message'] ?? 'Transaction validation failed.');
                return ['success' => false, 'error' => $errorMsg, 'raw_response' => $json];
            }

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

    protected function pollTransactionStatus(string $ref): array
    {
        Log::info('[LegacyBackend] Polling transaction status', ['ref' => $ref]);

        for ($i = 0; $i < $this->pollAttempts; $i++) {
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

        Log::warning('[LegacyBackend] Poll max attempts reached', ['ref' => $ref]);
        return [
            'completed' => false,
            'failed' => false,
            'message' => 'Transaction still processing. Check again shortly.',
            'attempts' => $this->pollAttempts,
        ];
    }

    protected function formatPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '263') && strlen($digits) > 9) {
            $digits = '0' . substr($digits, 3);
        }

        if (! str_starts_with($digits, '0') && strlen($digits) <= 9) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    protected function resolvePaymentMethod(string $currency): string
    {
        $currency = strtolower($currency);

        return match ($currency) {
            'usd' => 'ecocash-usd',
            'zwg', 'zwl', 'zig' => 'ecocash',
            default => 'ecocash',
        };
    }

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

    public function initAirtime(array $params): array
    {
        Log::info('[LegacyBackend] Init airtime', $params);

        return [
            'success' => false,
            'error' => 'Airtime service is not yet implemented via legacy API.',
        ];
    }

    public function processAirtime(array $params): array
    {
        Log::info('[LegacyBackend] Process airtime', $params);

        $result = $this->initAirtime($params);
        if (!$result['success']) {
            return $result;
        }

        $ref = $result['body']['ref'] ?? null;
        if ($ref) {
            return $this->pollAirtimeStatus($ref);
        }

        return [
            'success' => false,
            'error' => 'Airtime purchase failed.',
        ];
    }

    protected function pollAirtimeStatus(string $ref): array
    {
        Log::info('[LegacyBackend] Polling airtime status', ['ref' => $ref]);

        return [
            'success' => false,
            'error' => 'Airtime polling not yet implemented.',
        ];
    }

    public function initBundle(array $params): array
    {
        Log::info('[LegacyBackend] Init bundle', $params);

        return [
            'success' => false,
            'error' => 'Bundle service is not yet implemented via legacy API.',
        ];
    }

    public function processBundle(array $params): array
    {
        Log::info('[LegacyBackend] Process bundle', $params);

        $result = $this->initBundle($params);
        if (!$result['success']) {
            return $result;
        }

        $ref = $result['body']['ref'] ?? null;
        if ($ref) {
            return $this->pollBundleStatus($ref);
        }

        return [
            'success' => false,
            'error' => 'Bundle purchase failed.',
        ];
    }

    protected function pollBundleStatus(string $ref): array
    {
        Log::info('[LegacyBackend] Polling bundle status', ['ref' => $ref]);

        return [
            'success' => false,
            'error' => 'Bundle polling not yet implemented.',
        ];
    }

    public function validateTelone(string $accountNumber, string $currency = 'ZWG'): array
    {
        Log::info('[LegacyBackend] Validate TelOne account', [
            'account' => $accountNumber,
            'currency' => $currency,
        ]);

        return [
            'success' => false,
            'error' => 'TelOne validation not yet implemented via legacy API.',
        ];
    }

    public function initTelone(array $params): array
    {
        Log::info('[LegacyBackend] Init TelOne', $params);

        return [
            'success' => false,
            'error' => 'TelOne service is not yet implemented via legacy API.',
        ];
    }

    public function processTelone(array $params): array
    {
        Log::info('[LegacyBackend] Process TelOne', $params);

        $result = $this->initTelone($params);
        if (!$result['success']) {
            return $result;
        }

        $ref = $result['body']['ref'] ?? null;
        if ($ref) {
            return $this->pollTeloneStatus($ref);
        }

        return [
            'success' => false,
            'error' => 'TelOne purchase failed.',
        ];
    }

    protected function pollTeloneStatus(string $ref): array
    {
        Log::info('[LegacyBackend] Polling TelOne status', ['ref' => $ref]);

        return [
            'success' => false,
            'error' => 'TelOne polling not yet implemented.',
        ];
    }

    public function getBillers(): array
    {
        Log::info('[LegacyBackend] Get billers');

        return [
            'success' => false,
            'error' => 'Billers service is not yet implemented via legacy API.',
        ];
    }

    public function validateBiller(string $billerName, string $accountNumber): array
    {
        Log::info('[LegacyBackend] Validate biller account', [
            'biller' => $billerName,
            'account' => $accountNumber,
        ]);

        return [
            'success' => false,
            'error' => 'Biller validation not yet implemented via legacy API.',
        ];
    }

    public function processBillerPayment(array $params): array
    {
        Log::info('[LegacyBackend] Process biller payment', $params);

        return [
            'success' => false,
            'error' => 'Biller payment service is not yet implemented via legacy API.',
        ];
    }
}
