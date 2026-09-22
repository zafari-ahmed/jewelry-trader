<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Rental Services. Tables only, gated by features.rental.enabled.
 * The inventory_locks enum already carries "rental", so no migration is
 * needed there when this is switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            // Money in minor units, like every other money column.
            $table->bigInteger('deposit_amount_cents')->default(0);
            $table->string('deductible_option', 64)->nullable();
            $table->enum('status', ['draft', 'active', 'returned', 'overdue', 'cancelled'])->default('draft');
            $table->timestamps();
        });

        Schema::create('rental_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_agreement_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->bigInteger('amount_cents')->nullable();
            $table->enum('status', ['open', 'assessed', 'settled', 'rejected'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_claims');
        Schema::dropIfExists('rental_agreements');
    }
};
