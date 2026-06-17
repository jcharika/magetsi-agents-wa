<?php

use App\Http\Controllers\CustomerFlowController;
use App\Http\Controllers\CustomerVerifyWebhookController;
use App\Http\Controllers\CustomerWebhookController;
use App\Http\Controllers\FlowDataController;
use App\Http\Controllers\WhatsAppVerifyWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Webhook Routes — Agent Bot
|--------------------------------------------------------------------------
*/

Route::get('webhook', WhatsAppVerifyWebhookController::class);
Route::post('webhook', WhatsAppWebhookController::class);
Route::post('flow-data', FlowDataController::class);

/*
|--------------------------------------------------------------------------
| WhatsApp Webhook Routes — Customer Bot
|--------------------------------------------------------------------------
|
| Configure these URLs in your Meta Business Manager for the customer
| WhatsApp Business Account. Each bot uses its own WhatsApp credentials.
|
*/

Route::prefix('customer')->name('customer.')->group(function () {
    Route::get('webhook', CustomerVerifyWebhookController::class)->name('webhook.verify');
    Route::post('webhook', CustomerWebhookController::class)->name('webhook.message');
    Route::post('flow-data', CustomerFlowController::class)->name('flow-data');
});
