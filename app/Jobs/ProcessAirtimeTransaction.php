<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\Transaction;
use App\Services\BackendManager;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAirtimeTransaction implements ShouldQueue
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

        $transaction = Transaction::create([
            'agent_id' => $agent->id,
            'product_id' => 'airtime',
            'handler' => 'customer_flow',
            'meter_number' => $this->params['phone_number'] ?? '',
            'amount' => $this->params['amount'] ?? 0,
            'currency' => $this->params['currency'] ?? 'ZWG',
            'ecocash_number' => $this->params['ecocash_number'] ?? '',
            'recipient_phone' => $this->params['phone_number'] ?? null,
            'status' => 'processing',
            'api_response' => [],
        ]);

        Log::info('Queue: Processing airtime purchase', [
            'transaction_id' => $transaction->id,
            'network' => $this->params['network'],
            'phone' => $this->params['phone_number'],
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

                $this->notifyFailure($whatsapp, $agent, $transaction, $result['error'] ?? 'Airtime purchase failed');
            }
        } catch (\Throwable $e) {
            Log::error('Queue: Airtime purchase exception', [
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

        $message = "✅ *Airtime Purchase Successful*\n\n"
            . "Network: {$this->params['network']}\n"
            . "Phone: {$transaction->recipient_phone}\n"
            . "Amount: {$transaction->currency} {$transaction->amount}\n"
            . "Reference: {$ref}\n"
            . "Status: {$transaction->status}";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }

    protected function notifyFailure(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, string $reason): void
    {
        $message = "❌ *Airtime Purchase Failed*\n\n"
            . "Network: {$this->params['network']}\n"
            . "Phone: {$transaction->recipient_phone}\n"
            . "Amount: {$transaction->currency} {$transaction->amount}\n"
            . "Reason: {$reason}\n\n"
            . "Please try again or contact support if this persists.";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }
}