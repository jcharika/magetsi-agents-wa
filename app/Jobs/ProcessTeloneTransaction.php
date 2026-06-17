<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\BackendManager;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTeloneTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    protected array $params;
    protected array $userData;
    protected string $flowToken;

    public function __construct(array $params, array $userData, string $flowToken)
    {
        $this->params = $params;
        $this->userData = $userData;
        $this->flowToken = $flowToken;
    }

    public function handle(BackendManager $backend, WhatsAppService $whatsapp): void
    {
        $isCustomer = empty($this->userData['ecocash_number']);

        if ($isCustomer) {
            $whatsapp = WhatsAppService::forCustomer();

            $customer = Customer::firstOrCreate(
                ['wa_id' => $this->userData['wa_id']],
                ['name' => $this->userData['name'] ?? 'Customer', 'phone' => $this->userData['wa_id']],
            );

            $transaction = Transaction::create([
                'customer_id' => $customer->id,
                'product_id' => 'telone',
                'handler' => $this->params['handler'] ?? 'TELONE',
                'amount' => $this->params['amount'] ?? 0,
                'currency' => $this->params['currency'] ?? 'ZWG',
                'ecocash_number' => $this->params['ecocash_number'] ?? '',
                'status' => 'processing',
                'api_response' => [],
            ]);

            $this->processAndNotify($backend, $whatsapp, $customer, $transaction);
        } else {
            $agent = Agent::firstOrNew(['wa_id' => $this->userData['wa_id']], [
                'phone' => $this->userData['wa_id'],
                'name' => $this->userData['name'] ?? 'Customer',
                'ecocash_number' => $this->userData['ecocash_number'] ?? '',
            ]);
            $agent->save();

            $transaction = Transaction::create([
                'agent_id' => $agent->id,
                'product_id' => 'telone',
                'handler' => $this->params['handler'] ?? 'TELONE',
                'amount' => $this->params['amount'] ?? 0,
                'currency' => $this->params['currency'] ?? 'ZWG',
                'ecocash_number' => $this->params['ecocash_number'] ?? '',
                'status' => 'processing',
                'api_response' => [],
            ]);

            $this->processAndNotify($backend, $whatsapp, $agent, $transaction);
        }
    }

    protected function processAndNotify(BackendManager $backend, WhatsAppService $whatsapp, Agent|Customer $actor, Transaction $transaction): void
    {
        Log::debug('Queue: Processing TelOne transaction', [
            'transaction_id' => $transaction->id,
            'account' => $this->params['biller_account'],
            'package' => $this->params['package'],
        ]);

        try {
            $result = $backend->processTransaction($this->params);

            if ($result['success']) {
                $txn = $result['transaction'] ?? [];

                $transaction->update([
                    'status' => $txn['status'] ?? 'completed',
                    'token' => $txn['token'] ?? null,
                    'reference' => $txn['customer_reference'] ?? $txn['reference'] ?? $txn['uid'] ?? null,
                    'uid' => $txn['uid'] ?? null,
                    'external_uid' => $txn['external_uid'] ?? null,
                    'api_response' => $result,
                ]);

                $this->notifySuccess($whatsapp, $actor, $transaction, $txn);
            } else {
                $transaction->update([
                    'status' => 'failed',
                    'api_response' => $result,
                ]);

                $this->notifyFailure($whatsapp, $actor, $transaction, $result['error'] ?? 'TelOne purchase failed');
            }
        } catch (\Throwable $e) {
            Log::error('Queue: TelOne purchase exception', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $transaction->update([
                'status' => 'failed',
                'api_response' => ['error' => $e->getMessage()],
            ]);

            $this->notifyFailure($whatsapp, $actor, $transaction, $e->getMessage());

            throw $e;
        }
    }

    protected function notifySuccess(WhatsAppService $whatsapp, Agent|Customer $actor, Transaction $transaction, array $txn): void
    {
        $ref = $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—';

        $message = "✅ *TelOne Purchase Successful*\n\n"
            . "Account: {$this->params['biller_account']}\n"
            . "Package: {$this->params['package']}\n"
            . "Reference: {$ref}\n"
            . "Status: {$transaction->status}";

        $whatsapp->sendTextMessage($actor->wa_id, $message);
    }

    protected function notifyFailure(WhatsAppService $whatsapp, Agent|Customer $actor, Transaction $transaction, string $reason): void
    {
        $message = "❌ *TelOne Purchase Failed*\n\n"
            . "Account: {$this->params['biller_account']}\n"
            . "Package: {$this->params['package']}\n"
            . "Reason: {$reason}\n\n"
            . "Please try again or contact support if this persists.";

        $whatsapp->sendTextMessage($actor->wa_id, $message);
    }
}
