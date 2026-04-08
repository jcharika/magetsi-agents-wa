<?php

namespace App\Services;

use App\Contracts\TransactionBackend;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Magetsi backend API (new platform).
 *
 * Wraps the prepare → validate → confirm → process transaction lifecycle
 * as documented in api.md.
 */
class MagetsiApiService implements TransactionBackend
{
    protected string $baseUrl;
    protected string $channel;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('magetsi.url', 'https://magetsi.test'), '/');
        $this->channel = config('magetsi.channel', 'AGENTS');
        $this->timeout = config('magetsi.timeout', 30);
    }

    public function getBackendName(): string
    {
        return 'new';
    }

    // ── TransactionBackend interface ─────────────────

    public function validateMeter(string $meterNumber): array
    {
        Log::info('[NewBackend] Validating meter', ['meter' => $meterNumber]);

        $digits = preg_replace('/\D/', '', $meterNumber);

        if (strlen($digits) !== 11) {
            return ['valid' => false, 'error' => 'Meter number must be exactly 11 digits.'];
        }

        $prepare = $this->prepare('ZESA');
        if (! $prepare['success']) {
            return ['valid' => false, 'error' => $prepare['error'] ?? 'Service temporarily unavailable.'];
        }

        $trace = $prepare['trace'];
        $validation = $this->validate('ZESA', $trace, $digits);

        if (! $validation['success']) {
            return ['valid' => false, 'error' => $validation['error'] ?? 'Meter not found.'];
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

    public function processTransaction(array $params): array
    {
        Log::info('[NewBackend] Processing transaction', $params);

        $meterNumber = $params['meter_number'];
        $amount = (float) $params['amount'];
        $currency = $params['currency'];
        $ecocashNumber = $params['ecocash_number'];
        $recipientName = $params['recipient_name'];
        $recipientAddress = $params['recipient_address'];
        $recipientCurrency = $params['recipient_currency'] ?? $currency;
        $trace = $params['trace'];
        $debit = $params['debit'] ?? [];
        $guestId = $params['guest_id'] ?? '';

        // Find EcoCash config
        $ecocashConfig = collect($debit)->firstWhere('handler', 'ECOCASH');
        if (! $ecocashConfig) {
            return ['success' => false, 'error' => 'EcoCash payment not available for this meter.'];
        }

        $payment = [$this->buildEcocashPayment($ecocashNumber, $amount, $currency, $ecocashConfig)];

        // Confirm
        $confirmation = $this->confirm(
            'ZESA', $trace, $meterNumber, $amount, $currency,
            $recipientName, $recipientAddress, $recipientCurrency,
            $payment, $guestId
        );

        if (! $confirmation['success']) {
            return ['success' => false, 'error' => $confirmation['error'] ?? 'Confirmation failed.'];
        }

        // Process
        $confirmedPayment = $confirmation['payment'] ?? $payment;
        $processPayment = [];
        foreach ($confirmedPayment as $p) {
            $processPayment[] = $this->buildEcocashPayment(
                $p['account'] ?? $ecocashNumber,
                $p['amount'] ?? $amount,
                $p['currency'] ?? $currency,
                $ecocashConfig
            );
        }

        $processResult = $this->process(
            'ZESA', $trace, $meterNumber, $amount, $currency,
            $recipientName, $recipientAddress, $recipientCurrency,
            $processPayment, $guestId
        );

        if (! $processResult['success']) {
            return ['success' => false, 'error' => $processResult['error'] ?? 'Processing failed.'];
        }

        $txn = $processResult['transaction'] ?? [];
        $payments = $processResult['payments'] ?? [];

        return [
            'success' => true,
            'transaction' => [
                'status' => $txn['status'] ?? 'PENDING',
                'uid' => $txn['uid'] ?? null,
                'external_uid' => $txn['external_uid'] ?? null,
                'customer_reference' => $txn['customer_reference'] ?? null,
                'payment_amount' => $txn['payment_amount'] ?? null,
                'biller_status' => $txn['biller_status'] ?? null,
                'payment_status' => $txn['payment_status'] ?? null,
                'reference' => $payments[0]['reference'] ?? $txn['uid'] ?? null,
            ],
            'confirmation' => $confirmation,
            'raw_response' => $processResult,
        ];
    }

    // ── Low-level API methods (unchanged) ────────────

    /**
     * Step 1: Prepare — get transaction type info, payment methods, and a trace ID.
     */
    public function prepare(string $handler = 'ZESA'): array
    {
        $response = $this->post('/transactions/prepare', [
            'handler' => $handler,
            'channel' => $this->channel,
        ]);

        if (! $response['success']) {
            Log::error('Magetsi prepare failed', $response);
            return ['success' => false, 'error' => 'Failed to prepare transaction.'];
        }

        return [
            'success' => true,
            'trace' => $response['trace'],
            'transaction_type' => $response['transaction_type'],
            'payment_currencies' => $response['transaction_type']['payment_currency'] ?? [],
            'debit_types' => $response['transaction_type']['debit_types'] ?? [],
            'simulation' => $response['transaction_type']['simulation'] ?? false,
        ];
    }

    /**
     * Step 2: Validate — validate the meter (biller_account) and get customer info.
     *
     * Returns customer name, address, currency, and available debit types
     * filtered for this specific meter/account.
     */
    public function validate(string $handler, string $trace, string $billerAccount, array $extra = []): array
    {
        $payload = array_merge([
            'handler' => $handler,
            'channel' => $this->channel,
            'owner' => 'GUEST',
            'origination' => 'TRANSACTION',
            'user_id' => '',
            'guest_id' => '',
            'trace' => $trace,
            'uid' => '',
            'biller_account' => $billerAccount,
            'amount' => '',
            'product_code' => '',
            'currency' => '',
            'recipient_name' => '',
            'recipient_last_name' => '',
            'recipient_address' => '',
            'recipient_currency' => '',
            'recipient_phone' => '',
            'recipient_email' => '',
            'narration' => '',
            'payment' => [],
            'event_id' => '',
            'ticket_type_id' => '',
            'quantity' => 1,
            'biller_id' => '',
            'gift_voucher_id' => '',
            'voucher_type_id' => '',
            'message' => '',
        ], $extra);

        $response = $this->post('/transactions/validate', $payload);

        if (! ($response['success'] ?? false)) {
            $error = $response['message'] ?? $response['error'] ?? 'Meter validation failed.';
            Log::warning('Magetsi validate failed', ['biller_account' => $billerAccount, 'response' => $response]);
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => true,
            'recipient_name' => $response['recipient_name'] ?? '',
            'recipient_address' => $response['recipient_address'] ?? '',
            'biller_account' => $response['biller_account'] ?? $billerAccount,
            'recipient_currency' => $response['recipient_currency'] ?? '',
            'currency' => $response['currency'] ?? '',
            'debit' => $response['debit'] ?? [],
            'trace' => $response['trace'] ?? $trace,
            'bundles' => $response['bundles'] ?? [],
        ];
    }

    /**
     * Step 3: Confirm — get pricing breakdown before processing.
     *
     * Returns fees, discounts, bonuses, loyalty, and the formatted confirmation details.
     */
    public function confirm(
        string $handler,
        string $trace,
        string $billerAccount,
        float $amount,
        string $currency,
        string $recipientName,
        string $recipientAddress,
        string $recipientCurrency,
        array $payment,
        string $guestId = '',
        array $extra = []
    ): array {
        $payload = array_merge([
            'handler' => $handler,
            'channel' => $this->channel,
            'owner' => 'GUEST',
            'origination' => 'TRANSACTION',
            'user_id' => '',
            'guest_id' => $guestId,
            'trace' => $trace,
            'uid' => '',
            'biller_account' => $billerAccount,
            'amount' => $amount,
            'product_code' => '',
            'currency' => $currency,
            'recipient_name' => $recipientName,
            'recipient_last_name' => '',
            'recipient_address' => $recipientAddress,
            'recipient_currency' => $recipientCurrency,
            'recipient_phone' => '',
            'recipient_email' => '',
            'narration' => '',
            'payment' => $payment,
            'event_id' => '',
            'ticket_type_id' => '',
            'quantity' => 1,
            'biller_id' => '',
            'gift_voucher_id' => '',
            'voucher_type_id' => '',
            'message' => '',
        ], $extra);

        $response = $this->post('/transactions/confirm', $payload);

        if (! ($response['success'] ?? false)) {
            $error = $response['message'] ?? $response['error'] ?? 'Confirmation failed.';
            Log::warning('Magetsi confirm failed', ['response' => $response]);
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => true,
            'confirmation' => $response['confirmation'] ?? [],
            'payment' => $response['payment'] ?? [],
            'amounts' => $response['amounts'] ?? [],
        ];
    }

    /**
     * Step 4: Process — execute the transaction.
     *
     * Returns the transaction record with status, reference, and payment info.
     */
    public function process(
        string $handler,
        string $trace,
        string $billerAccount,
        float $amount,
        string $currency,
        string $recipientName,
        string $recipientAddress,
        string $recipientCurrency,
        array $payment,
        string $guestId = '',
        array $extra = []
    ): array {
        $payload = array_merge([
            'handler' => $handler,
            'channel' => $this->channel,
            'owner' => 'GUEST',
            'origination' => 'TRANSACTION',
            'user_id' => '',
            'guest_id' => $guestId,
            'trace' => $trace,
            'uid' => '',
            'biller_account' => $billerAccount,
            'amount' => $amount,
            'product_code' => '',
            'currency' => $currency,
            'recipient_name' => $recipientName,
            'recipient_last_name' => '',
            'recipient_address' => $recipientAddress,
            'recipient_currency' => $recipientCurrency,
            'recipient_phone' => '',
            'recipient_email' => '',
            'narration' => '',
            'payment' => $payment,
            'event_id' => '',
            'ticket_type_id' => '',
            'quantity' => 1,
            'biller_id' => '',
            'gift_voucher_id' => '',
            'voucher_type_id' => '',
            'message' => '',
        ], $extra);

        $response = $this->post('/transactions/process', $payload);

        if (! ($response['success'] ?? false)) {
            $error = $response['message'] ?? $response['error'] ?? 'Transaction processing failed.';
            Log::error('Magetsi process failed', ['response' => $response]);
            return ['success' => false, 'error' => $error];
        }

        return [
            'success' => true,
            'transaction' => $response['transaction'] ?? [],
            'payments' => $response['payments'] ?? [],
            'redirect' => $response['redirect'] ?? false,
            'url' => $response['url'] ?? null,
        ];
    }

    /**
     * Fetch available currencies.
     */
    public function currencies(): array
    {
        $response = $this->get('/currency/list');
        return $response['body']['list'] ?? [];
    }

    /**
     * Fetch available channels.
     */
    public function channels(): array
    {
        $response = $this->get('/channel/list');
        return $response['body']['list'] ?? [];
    }

    /**
     * Build the payment array for an EcoCash payment.
     */
    public function buildEcocashPayment(string $account, float $amount, string $currency, array $ecocashConfig = []): array
    {
        $config = $ecocashConfig ?: [
            'handler' => 'ECOCASH',
            'name' => 'EcoCash',
            'description' => 'EcoCash Payment',
            'requires_account' => true,
            'redirect' => false,
            'requires_authorisation' => false,
            'currency' => [$currency],
        ];

        return [
            'configuration' => $config,
            'handler' => 'ECOCASH',
            'account' => $account,
            'amount' => $amount,
            'currency' => $currency,
            'verified' => (object) [],
            'isVerified' => false,
        ];
    }

    // ── HTTP helpers ──────────────────────────────

    protected function post(string $path, array $data): array
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout($this->timeout)
                ->post($this->baseUrl . $path, $data);

            return $response->json() ?? ['success' => false, 'error' => 'Empty response'];
        } catch (\Throwable $e) {
            Log::error('Magetsi API error', ['path' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function get(string $path): array
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout($this->timeout)
                ->get($this->baseUrl . $path);

            return $response->json() ?? ['success' => false, 'error' => 'Empty response'];
        } catch (\Throwable $e) {
            Log::error('Magetsi API error', ['path' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
