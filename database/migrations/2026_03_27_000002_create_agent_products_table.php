<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('product_id')->comment('e.g. zesa, airtime');
            $table->string('label')->comment('e.g. ZESA Tokens');
            $table->string('icon')->default('⚡');
            $table->string('currency')->default('ZWG');
            $table->integer('min_amount')->default(100);
            $table->json('quick_amounts')->comment('Array of 4 quick amounts');
            $table->timestamps();

            $table->unique(['agent_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_products');
    }
};
