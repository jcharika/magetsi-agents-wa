<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Magetsi backend API.
 *
 * Wraps the prepare → validate → confirm → process transaction lifecycle
 * as documented in api.md.
 */
class MagetsiApiService
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
