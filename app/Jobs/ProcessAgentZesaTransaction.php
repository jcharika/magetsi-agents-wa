<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Transaction;
use App\Jobs\Traits\ProcessZesaTransactionTrait;
use App\Services\BackendManager;
use App\Services\WhatsAppService;
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

    public int $tries = 1;
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

    /**
     * @throws \Throwable
     */
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

        Log::debug('Queue: Processing agent ZESA transaction', [
            'transaction_id' => $transaction->id,
            'meter_number' => $this->params['meter_number'],
            'amount' => $this->params['amount'],
        ]);

        $this->processZesaTransaction($backend, $whatsapp, $agent, $transaction, $this->params);
    }

    protected function notifySuccess(WhatsAppService $whatsapp, Agent|Customer $agent, Transaction $transaction, array $txn): void
    {
        $rawResponse = $transaction->api_response['raw_response'] ?? [];
        $details = $rawResponse['body']['details'] ?? $rawResponse['poll_result']['details'] ?? [];

        if ($details) {
            $currency = $details['currency'] ?? $transaction->currency ?? 'ZWG';
            $token = trim($details['token'] ?? '');
            $meter = $details['meter'] ?? $transaction->meter_number ?? '';
            $kwh = $details['energyBought'] ?? '0.00';
            $amount = (float)($details['amount'] ?? $transaction->amount ?? 0);
            $levy = (float)($details['levy'] ?? 0);
            $ratedAmount = (float)($details['rated_amount'] ?? $amount);
            $debt = 0.00;
            $energy = max($amount - $levy - $debt, 0);
            $date = now()->format('d/m/y H:i');

            $message = "Token: {$token}\n"
                . "Meter: {$meter}\n"
                . "KwH: {$kwh}\n"
                . "Energy: {$currency}" . number_format($energy, 2) . "\n"
                . "Debt: {$currency}" . number_format($debt, 2) . "\n"
                . "REA: {$currency}" . number_format($levy, 2) . "\n"
                . "Total Amt: {$currency}" . number_format($amount, 2) . "\n"
                . "Tendered: {$currency}" . number_format($ratedAmount, 2) . "\n"
                . "{$date}";
        } else {
            $ref = $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—';
            $token = $txn['token'] ?? $transaction->token ?? 'pending';
            $phone = $transaction->recipient_phone ?? $transaction->ecocash_number;

            $message = "✅ *ZESA Purchase Successful*\n\n"
                . "Meter: {$transaction->meter_number}\n"
                . "Amount: {$transaction->currency} {$transaction->amount}\n"
                . "Reference: {$ref}\n"
                . ($token == 'pending' ? '' : "Token: {$token}\n\n")
                . "Your token has been sent to {$phone}.";
        }

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }

    protected function notifyFailure(WhatsAppService $whatsapp, Agent|Customer $agent, Transaction $transaction, string $reason): void
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

    protected function notifyPending(WhatsAppService $whatsapp, Agent|Customer $agent, Transaction $transaction, array $txn): void
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
