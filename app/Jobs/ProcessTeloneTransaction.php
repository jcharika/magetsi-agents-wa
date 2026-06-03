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

class ProcessTeloneTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

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
        $handler = $this->params['handler'] ?? 'TELONE';

        $transaction = Transaction::create([
            'agent_id' => $agent->id,
            'product_id' => 'telone',
            'handler' => $handler,
            'meter_number' => $this->params['biller_account'] ?? '',
            'amount' => 0,
            'currency' => $currency,
            'ecocash_number' => $this->params['ecocash_number'] ?? '',
            'recipient_phone' => $this->params['phone_number'] ?? null,
            'status' => 'processing',
            'api_response' => [],
        ]);

        Log::debug('Queue: Processing TelOne purchase', [
            'transaction_id' => $transaction->id,
            'handler' => $handler,
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

                $this->notifySuccess($whatsapp, $agent, $transaction, $txn, $currency);
            } else {
                $transaction->update([
                    'status' => 'failed',
                    'api_response' => $result,
                ]);

                $this->notifyFailure($whatsapp, $agent, $transaction, $result['error'] ?? 'TelOne purchase failed');
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

            $this->notifyFailure($whatsapp, $agent, $transaction, $e->getMessage());

            throw $e;
        }
    }

    protected function notifySuccess(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, array $txn, string $currency): void
    {
        $ref = $txn['customer_reference'] ?? $txn['reference'] ?? $transaction->reference ?? '—';
        $currencyLabel = $currency === 'USD' ? 'USD' : 'ZWG';

        $message = "✅ *TelOne WiFi Purchase Successful*\n\n"
            . "Account: {$this->params['biller_account']}\n"
            . "Package: {$this->params['package']}\n"
            . "Phone: {$transaction->recipient_phone}\n"
            . "Reference: {$ref}\n"
            . "Status: {$transaction->status}";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }

    protected function notifyFailure(WhatsAppService $whatsapp, Agent $agent, Transaction $transaction, string $reason): void
    {
        $message = "❌ *TelOne WiFi Purchase Failed*\n\n"
            . "Account: {$this->params['biller_account']}\n"
            . "Package: {$this->params['package']}\n"
            . "Phone: {$transaction->recipient_phone}\n"
            . "Reason: {$reason}\n\n"
            . "Please try again or contact support if this persists.";

        $whatsapp->sendTextMessage($agent->wa_id, $message);
    }
}