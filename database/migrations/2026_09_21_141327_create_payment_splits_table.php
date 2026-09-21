<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per tender on a split payment. The parent payment's amount is the
 * sum of its splits — enforced in PaymentService, proven in tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['card', 'cash']);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_refunded')->default(0);
            $table->string('gateway_transaction_id')->nullable();
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded', 'partially_refunded', 'voided'])->default('pending');
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_splits');
    }
};
