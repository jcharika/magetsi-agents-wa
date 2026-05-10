<?php

namespace App\Jobs;

use Magetsi\Common\Models\Agent;
use Magetsi\Common\Models\Transaction;
use Magetsi\Common\Services\BackendManager;
use Magetsi\Common\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBillerTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    protected array $params;
    protected array $agentData;
    protected string $flowToken;

    public function __construct(array $params, array $agentData, string $flowToken)
    {
        $this->params = $params;
        $this->agentData = $agentData;
        $this->flowToken = $flowToken;
    }

    public function handle(BackendManager $backend, WhatsAppService $whatsapp): void
    {
        $agent = Agent::firstOrNew(['wa_id' => $this->agentData['wa_id']], [
            'phone' => $this->agentData['wa_id'],
            'name' => $this->agentData['name'] ?? 'Customer',
            'ecocash_number' => $this->agentData['ecocash_number'] ?? '',
        ]);
        $agent->save();

        $currency = $this->params['currency'] ?? 'ZWG';

        $transaction = Transaction::create([
            'agent_id' => $agent->id,
            'product_id' => 'biller',
            'handler' => 'BILLERS',
            'meter_number' => $this->params['biller_account'] ?? '',
            'amount' => $this->params['amount'] ?? 0,
            'currency' => $currency,
            'ecocash_number' => $this->params['ecocash_number'] ?? '',
            'recipient_phone' => null,
            'status' => 'processing',
            'api_response' => [],
        ]);

        Log::info('Queue: Processing biller payment', [
            'transaction_id' => $transaction->id,
            'biller' => $this->params['biller_name'],
            'account' => $this->params['biller_account'],
            'amount' => $this->params['amount'],
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

                $this->notifySuccess($whatsapp, $agent, $transaction, $txn);
            } else {
                $transaction->update([
                    'status' => 'failed',
                    'api_response' => $result,
                ]);

                $this->notifyFailure($whatsapp, $agent, $transaction, $result['error'] ?? 'Payment failed');
            }
        } catch (\Throwable $e) {
            Log::error('Queue: Biller payment exception', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $transaction->update([
                'status' => 'failed',
                'api_response' => ['error' => $e->getMessage()],
            ]);

            $this->notifyFailure($whatsapp, $agent, $transaction, $e->getMessage());

            throw $e;
        }
    }

    protected function notifySuccess(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, array $txn): void
    {
        $ref = $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—';
        $currency = $this->params['currency'] ?? 'ZWG';

        $message = "✅ *Bill Payment Successful*\n\n"
            . "Biller: {$this->params['biller_name']}\n"
            . "Account: {$this->params['biller_account']}\n"
            . "Amount: {$currency} {$this->params['amount']}\n"
            . "Reference: {$ref}\n"
            . "Status: {$transaction->status}";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }

    protected function notifyFailure(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, string $reason): void
    {
        $currency = $this->params['currency'] ?? 'ZWG';

        $message = "❌ *Bill Payment Failed*\n\n"
            . "Biller: {$this->params['biller_name']}\n"
            . "Account: {$this->params['biller_account']}\n"
            . "Amount: {$currency} {$this->params['amount']}\n"
            . "Reason: {$reason}\n\n"
            . "Please try again or contact support if this persists.";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }
}