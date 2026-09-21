<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money is stored in minor units (integer cents) throughout, matching the
 * payments tables. Column names carry the _cents suffix so a caller cannot
 * mistake $68.00 for 6800 — see docs/DECISIONS.md.
 *
 * Rows are append-only history: the current price is the latest row, so a
 * price change never destroys what the item was previously offered at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('acquisition_value_cents')->nullable();
            $table->unsignedBigInteger('retail_price_cents')->nullable();
            $table->unsignedBigInteger('insurance_value_cents')->nullable();
            $table->unsignedBigInteger('negotiation_min_cents')->nullable();
            $table->unsignedBigInteger('promo_price_cents')->nullable();
            // Null when a Phase 2 AI suggestion set the price rather than a person.
            $table->foreignId('priced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing');
    }
};
