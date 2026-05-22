<?php

namespace App\Jobs\Traits;

use App\Models\Agent;
use App\Models\Transaction;
use App\Services\BackendManager;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

trait ProcessZesaTransactionTrait
{
    /**
     * @throws \Throwable
     */
    protected function processZesaTransaction(
        BackendManager $backend,
        WhatsAppService $whatsapp,
        Agent $agent,
        Transaction $transaction,
        array $params
    ): void {
        try {
            $result = $backend->processTransaction($params);

            if ($result['success']) {
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
                        'trace' => $params['trace'] ?? null,
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
                        'reference' => $txn['customer_reference'] ?? $txn['reference'] ?? $txn['uid'] ?? null,
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

    abstract protected function notifySuccess(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, array $txn): void;
    abstract protected function notifyFailure(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, string $reason): void;
    abstract protected function notifyPending(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, array $txn): void;
}
