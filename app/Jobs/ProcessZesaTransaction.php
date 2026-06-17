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

class ProcessZesaTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use ProcessZesaTransactionTrait;

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
                'product_id' => 'zesa',
                'handler' => 'customer_flow',
                'meter_number' => $this->params['meter_number'] ?? '',
                'amount' => $this->params['amount'] ?? 0,
                'currency' => $this->params['currency'] ?? 'ZWG',
                'ecocash_number' => $this->params['ecocash_number'] ?? '',
                'recipient_phone' => $this->params['recipient_phone'] ?? null,
                'status' => 'processing',
                'api_response' => [],
            ]);

            Log::debug('Queue: Processing customer ZESA transaction', [
                'transaction_id' => $transaction->id,
                'customer_id' => $customer->id,
                'meter_number' => $this->params['meter_number'],
                'amount' => $this->params['amount'],
            ]);

            $this->processZesaTransaction($backend, $whatsapp, $customer, $transaction, $this->params);
        } else {
            $agent = Agent::firstOrNew(['wa_id' => $this->userData['wa_id']], [
                'phone' => $this->userData['wa_id'],
                'name' => $this->userData['name'] ?? 'Customer',
                'ecocash_number' => $this->userData['ecocash_number'] ?? '',
            ]);
            $agent->save();

            $transaction = Transaction::create([
                'agent_id' => $agent->id,
                'product_id' => 'zesa',
                'handler' => 'customer_flow',
                'meter_number' => $this->params['meter_number'] ?? '',
                'amount' => $this->params['amount'] ?? 0,
                'currency' => $this->params['currency'] ?? 'ZWG',
                'ecocash_number' => $this->params['ecocash_number'] ?? '',
                'recipient_phone' => $this->params['recipient_phone'] ?? null,
                'status' => 'processing',
                'api_response' => [],
            ]);

            Log::debug('Queue: Processing agent ZESA transaction', [
                'transaction_id' => $transaction->id,
                'agent_id' => $agent->id,
                'meter_number' => $this->params['meter_number'],
                'amount' => $this->params['amount'],
            ]);

            $this->processZesaTransaction($backend, $whatsapp, $agent, $transaction, $this->params);
        }
    }

    protected function notifySuccess(WhatsAppService $whatsapp, Agent|Customer $actor, Transaction $transaction, array $txn): void
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

            $message = "✅ *ZESA Purchase Successful*\n\n"
                . "Meter: {$transaction->meter_number}\n"
                . "Amount: {$transaction->currency} {$transaction->amount}\n"
                . "Reference: {$ref}\n"
                . "Token: {$token}\n\n"
                . "Your token has been sent to {$transaction->recipient_phone}.";
        }

        $whatsapp->sendTextMessage($actor->wa_id, $message);
    }

    protected function notifyFailure(WhatsAppService $whatsapp, Agent|Customer $actor, Transaction $transaction, string $reason): void
    {
        $message = "❌ *ZESA Purchase Failed*\n\n"
            . "Meter: {$transaction->meter_number}\n"
            . "Amount: {$transaction->currency} {$transaction->amount}\n"
            . "Reference: {$transaction->reference}\n"
            . "Status: {$transaction->status}\n\n"
            . "Reason: {$reason}\n\n"
            . "Please try again or contact support if this persists.";

        $whatsapp->sendTextMessage($actor->wa_id, $message);
    }

    protected function notifyPending(WhatsAppService $whatsapp, Agent|Customer $actor, Transaction $transaction, array $txn): void
    {
        $ref = $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—';

        $message = "⏳ *ZESA Purchase Pending*\n\n"
            . "Meter: {$transaction->meter_number}\n"
            . "Amount: {$transaction->currency} {$transaction->amount}\n"
            . "Reference: {$ref}\n\n"
            . "Your payment is being processed. You will receive another notification once completed.\n\n"
            . "If you don't receive the token within a few minutes, please check your EcoCash balance.";

        $whatsapp->sendTextMessage($actor->wa_id, $message);
    }
}
