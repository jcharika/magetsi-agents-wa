<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CustomerWebhookController extends Controller
{
    protected WhatsAppService $whatsapp;

    public function __construct()
    {
        $this->whatsapp = WhatsAppService::forCustomer();
    }

    /**
     * Handle incoming webhook events (POST).
     */
    public function __invoke(Request $request): Response
    {
        try {
            $body = $request->all();

            Log::debug('Customer webhook received', ['body' => json_encode($body, depth: 1024)]);

            $entries = $body['entry'] ?? [];

            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];

                foreach ($changes as $change) {
                    if (($change['field'] ?? '') !== 'messages') {
                        continue;
                    }

                    $value = $change['value'] ?? [];
                    $messages = $value['messages'] ?? [];
                    $contacts = $value['contacts'] ?? [];

                    foreach ($messages as $message) {
                        $this->processMessage($message, $contacts);
                    }
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Customer webhook error', ['error' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
        }

        return response('OK', 200);
    }

    /**
     * Process a single inbound customer message.
     */
    protected function processMessage(array $message, array $contacts): void
    {
        $messageId = $message['id'] ?? '';
        if ($messageId && !Cache::add("customer_msg:{$messageId}", true, 3600)) {
            Log::debug('Customer duplicate message skipped', ['message_id' => $messageId]);
            return;
        }

        $waId = $message['from'] ?? '';

        $contactName = null;
        foreach ($contacts as $contact) {
            if (($contact['wa_id'] ?? '') === $waId) {
                $contactName = $contact['profile']['name'] ?? null;
                break;
            }
        }

        $customer = Customer::query()->firstOrCreate(
            ['wa_id' => $waId],
            ['name' => $contactName, 'phone' => $waId],
        );

        if ($customer->blocked) {
            Log::info('Blocked customer attempted interaction', ['wa_id' => $waId]);
            $this->whatsapp->sendTextMessage(
                $waId,
                "❌ *Account Suspended*\n\nYour access to Magetsi has been suspended. Please contact support for assistance."
            );
            return;
        }

        $this->launchCustomerFlow($customer);
    }

    /**
     * Send the customer flow CTA.
     */
    protected function launchCustomerFlow(Customer $customer): void
    {
        $flowId = config('whatsapp.flows.customer');

        if (!$flowId) {
            $this->whatsapp->sendTextMessage(
                $customer->wa_id,
                "🛒 *Welcome to Magetsi*\n\nWelcome! How can I help you today?\n\n• Buy ZESA tokens\n• Buy airtime\n• Buy data bundles\n• Pay TelOne\n• Pay billers"
            );
            return;
        }

        $flowToken = $customer->wa_id . ':customer:' . Str::uuid()->toString();

        $this->whatsapp->sendFlow(
            $customer->wa_id,
            $flowId,
            $flowToken,
            null,
            null,
            'Browse Services',
            implode("\n", [
                '🛒 *Welcome to Magetsi*',
                'Magetsi is a leading digital payment platform in Zimbabwe providing an array of payment services through web and the Magetsi WhatsApp Chatbot.',
                'Tap the button below to get started'
            ]),
        );
    }
}
