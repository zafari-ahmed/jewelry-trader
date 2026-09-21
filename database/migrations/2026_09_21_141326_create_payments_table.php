<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PCI DSS: no raw card data is ever stored. Only the gateway's transaction id
 * and its response are kept; card details reach Stripe directly from the
 * client via a token.
 *
 * order_id has no foreign key yet — orders arrives in Module 4, which adds the
 * constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('gateway', 64);
            $table->string('gateway_transaction_id')->nullable()->index();
            // Amounts are stored in minor units (cents) to keep money in integers.
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_refunded')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded', 'partially_refunded', 'voided'])->default('pending');
            $table->enum('method', ['card', 'cash', 'split']);
            $table->json('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
