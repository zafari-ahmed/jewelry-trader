<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gateways are rows, not a hardcoded enum: adding PayPal later is a data row
 * plus a class implementing PaymentGatewayInterface (CLAUDE.md Module 1 & 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver_class');
            $table->boolean('is_active')->default(false);
            $table->boolean('supports_card')->default(true);
            $table->boolean('supports_cash')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
