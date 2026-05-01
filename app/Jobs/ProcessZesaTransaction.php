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

class ProcessZesaTransaction implements ShouldQueue
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

        Log::info('Queue: Processing ZESA transaction', [
            'transaction_id' => $transaction->id,
            'meter_number' => $this->params['meter_number'],
            'amount' => $this->params['amount'],
        ]);

        try {
            $result = $backend->processTransaction($this->params);

            if ($result['success']) {
                $txn = $result['transaction'] ?? [];

$txn = $result['transaction'] ?? [];
            $rawResponse = $result['raw_response'] ?? [];
            $pollResult = $rawResponse['poll_result'] ?? [];
            $isFailed = $pollResult['failed'] ?? false;
            $failureMessage = $pollResult['message'] ?? null;

            $successStatuses = ['COMPLETED', 'SUCCESS', 'completed', 'success'];
            $isCompleted = in_array($txn['status'] ?? '', $successStatuses, true);

            if ($isCompleted && !$isFailed) {
                $transaction->update([
                    'status' => 'completed',
                    'token' => $txn['token'] ?? null,
                    'reference' => $txn['customer_reference'] ?? $txn['reference'] ?? $txn['uid'] ?? null,
                    'uid' => $txn['uid'] ?? null,
                    'external_uid' => $txn['external_uid'] ?? null,
                    'trace' => $this->params['trace'] ?? null,
                    'biller_status' => $txn['biller_status'] ?? null,
                    'payment_status' => $txn['payment_status'] ?? null,
                    'payment_amount' => $txn['payment_amount'] ?? null,
                    'customer_reference' => $txn['customer_reference'] ?? null,
                    'api_response' => $result,
                ]);

                $this->notifySuccess($whatsapp, $agent, $transaction, $txn);
            } elseif ($isFailed) {
                $transaction->update([
                    'status' => 'failed',
                    'api_response' => $result,
                ]);

                $this->notifyFailure($whatsapp, $agent, $transaction, $failureMessage ?? 'Transaction failed to complete');
            } else {
                $transaction->update([
                    'status' => $txn['status'] ?? 'pending',
                    'token' => $txn['token'] ?? null,
                    'reference' => $txn['customer_reference'] ?? $txn['reference'] ?? $txn['uid'] ?? null,
                    'uid' => $txn['uid'] ?? null,
                    'external_uid' => $txn['external_uid'] ?? null,
                    'biller_status' => $txn['biller_status'] ?? null,
                    'payment_status' => $txn['payment_status'] ?? null,
                    'payment_amount' => $txn['payment_amount'] ?? null,
                    'customer_reference' => $txn['customer_reference'] ?? null,
                    'api_response' => $result,
                ]);

                $this->notifyPending($whatsapp, $agent, $transaction, $txn);
            }
            } else {
                $transaction->update([
                    'status' => 'failed',
                    'api_response' => $result,
                ]);

                $this->notifyFailure($whatsapp, $agent, $transaction, $result['error'] ?? 'Transaction failed');
            }
        } catch (\Throwable $e) {
            Log::error('Queue: ZESA transaction exception', [
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
        $token = $txn['token'] ?? $transaction->token ?? 'pending';

$message = "✅ *ZESA Purchase Successful*\n\n"
            . "Meter: {$transaction->meter_number}\n"
            . "Amount: {$transaction->currency} {$transaction->amount}\n"
            . "Reference: {$ref}\n"
            . "Status: {$transaction->status}\n"
            . "Token: {$token}\n\n"
            . "Your token has been sent to {$transaction->recipient_phone}.";

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
            . "Your payment is being processed. You will receive another notification once completed.\n\n"
            . "If you don't receive the token within a few minutes, please check your EcoCash balance.";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }
}