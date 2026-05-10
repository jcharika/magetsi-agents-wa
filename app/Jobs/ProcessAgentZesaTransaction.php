<?php

namespace App\Jobs;

use Magetsi\Common\Models\Agent;
use Magetsi\Common\Models\Transaction;
use Magetsi\Common\Traits\ProcessZesaTransactionTrait;
use Magetsi\Common\Services\BackendManager;
use Magetsi\Common\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAgentZesaTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use ProcessZesaTransactionTrait;

    public int $tries = 3;
    public int $timeout = 120;

    protected array $params;
    protected int $agentId;
    protected string $flowToken;

    public function __construct(array $params, int $agentId, string $flowToken)
    {
        $this->params = $params;
        $this->agentId = $agentId;
        $this->flowToken = $flowToken;
    }

    public function handle(BackendManager $backend, WhatsAppService $whatsapp): void
    {
        $agent = Agent::find($this->agentId);

        if (!$agent) {
            Log::error('Queue: Agent not found', ['agent_id' => $this->agentId]);
            return;
        }

        $transaction = Transaction::create([
            'agent_id' => $agent->id,
            'product_id' => 'zesa',
            'handler' => 'buy_zesa_flow',
            'meter_number' => $this->params['meter_number'] ?? '',
            'customer_name' => $this->params['recipient_name'] ?? '',
            'customer_address' => $this->params['recipient_address'] ?? '',
            'amount' => $this->params['amount'] ?? 0,
            'currency' => $this->params['currency'] ?? 'ZWG',
            'ecocash_number' => $this->params['ecocash_number'] ?? '',
            'recipient_phone' => $this->params['recipient_phone'] ?? null,
            'status' => 'processing',
            'api_response' => [],
        ]);

        Log::info('Queue: Processing agent ZESA transaction', [
            'transaction_id' => $transaction->id,
            'meter_number' => $this->params['meter_number'],
            'amount' => $this->params['amount'],
        ]);

        $this->processZesaTransaction($backend, $whatsapp, $agent, $transaction, $this->params);
    }

    protected function notifySuccess(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, array $txn): void
    {
        $ref = $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—';
        $token = $txn['token'] ?? $transaction->token ?? 'pending';

        $message = "✅ *ZESA Purchase Successful*\n\n"
            . "Meter: {$transaction->meter_number}\n"
            . "Amount: {$transaction->currency} {$transaction->amount}\n"
            . "Reference: {$ref}\n"
            . ($token == 'pending' ? '' : "Token: {$token}\n\n")
            . ($transaction->recipient_phone ? "Your token has been sent to {$transaction->recipient_phone}." : '');

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }

    protected function notifyFailure(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, string $reason): void
    {
        $message = "❌ *ZESA Purchase Failed*\n\n"
            . "Meter: {$transaction->meter_number}\n"
            . "Amount: {$transaction->currency} {$transaction->amount}\n"
            . "Reference: {$transaction->reference}\n"
            . "Status: {$transaction->status}\n\n"
            . "Reason: {$reason}\n\n"
            . "Please try again or contact support if this persists.";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }

    protected function notifyPending(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, array $txn): void
    {
        $ref = $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—';

        $message = "⏳ *ZESA Purchase Pending*\n\n"
            . "Meter: {$transaction->meter_number}\n"
            . "Amount: {$transaction->currency} {$transaction->amount}\n"
            . "Reference: {$ref}\n\n"
            . "We have sent a request to your mobile, please check and confirm the payment to proceed.\n\n"
            . "If you don't receive the request within a few minutes, please check your EcoCash balance.";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }
}