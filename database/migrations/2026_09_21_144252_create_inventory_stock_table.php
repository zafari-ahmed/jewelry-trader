<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-of-a-kind pieces are quantity=1 rows, so the same structure handles
 * future multi-quantity stock without a reshape (rule 3.7).
 *
 * The unique key on (product_id, location_id) is what makes the sold-once
 * guarantee enforceable: a row is locked FOR UPDATE before a sale commits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->enum('status', ['in_stock', 'reserved', 'sold', 'transferred'])->default('in_stock');
            $table->timestamps();

            $table->unique(['product_id', 'location_id']);
            $table->index(['location_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock');
    }
};
