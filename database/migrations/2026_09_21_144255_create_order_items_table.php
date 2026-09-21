<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_id is nullable: a line can be a service or non-inventory charge
 * (the design's "Ring sizing · SVC-011 · $145"), decided in Phase 1 because
 * retrofitting it onto live order data is expensive — docs/DECISIONS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            // Captured at sale time so the line survives a later title change.
            $table->string('description');
            $table->unsignedBigInteger('price_cents');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
