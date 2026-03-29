<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppVerifyWebhookController extends Controller
{
    /**
     * Webhook verification (GET) — Meta sends hub.challenge to verify.
     */
    public function __invoke(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('whatsapp.verify_token')) {
            Log::info('Webhook verified');
            return response($challenge, 200);
        }

        Log::warning('Webhook verification failed', [
            'mode' => $mode,
            'token' => $token,
        ]);

        return response('Forbidden', 403);
    }
}
