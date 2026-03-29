<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('product_id');
            $table->string('handler')->default('ZESA');
            $table->string('meter_number');
            $table->string('customer_name')->nullable();
            $table->string('customer_address')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('ZWG');
            $table->string('ecocash_number');
            $table->string('recipient_phone')->nullable()->comment('SMS delivery number');
            $table->string('status')->default('pending')->comment('pending, processing, completed, failed');
            $table->string('token')->nullable()->comment('ZESA token received');
            $table->string('reference')->nullable()->comment('EcoCash transaction ref');

            // Magetsi API trace fields
            $table->string('trace')->nullable()->comment('Magetsi API trace ID');
            $table->string('uid')->nullable()->comment('Magetsi transaction UID');
            $table->string('external_uid')->nullable()->comment('External reference from biller');
            $table->string('biller_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->decimal('payment_amount', 12, 2)->nullable()->comment('Actual payment after fees/discounts');
            $table->string('customer_reference')->nullable()->comment('Customer-facing reference');
            $table->json('api_response')->nullable()->comment('Full process response from Magetsi');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
