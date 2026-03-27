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
            $table->string('meter_number');
            $table->string('customer_name')->nullable();
            $table->string('customer_address')->nullable();
            $table->integer('amount');
            $table->string('currency')->default('ZWG');
            $table->string('ecocash_number');
            $table->string('recipient_phone')->nullable()->comment('SMS delivery number');
            $table->string('status')->default('pending')->comment('pending, paid, completed, failed');
            $table->string('token')->nullable()->comment('ZESA token received');
            $table->string('reference')->nullable()->comment('EcoCash transaction ref');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
