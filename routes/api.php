<?php

use App\Http\Controllers\FlowDataController;
use App\Http\Controllers\WhatsAppVerifyWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle the WhatsApp Cloud API webhook for incoming messages
| and the Flow Data Endpoint for dynamic flow interactions.
|
*/

Route::get('webhook', WhatsAppVerifyWebhookController::class);
Route::post('webhook', WhatsAppWebhookController::class);
Route::post('flow-data', FlowDataController::class);
