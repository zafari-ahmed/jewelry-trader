<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What was withheld from a refund, and why.
 *
 * A row per return rather than a running total on the order: a partial return
 * can happen more than once against the same order, and a fee that was waived
 * has to stay on the record as a waiver — the reason is the point of it.
 *
 * `goods_cents` is the basis the fee was calculated from, kept because the
 * percentage and the cap behind it are settings that will move. Without it a
 * fee charged last year cannot be explained this year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('goods_cents');
            $table->unsignedInteger('amount_cents');
            $table->decimal('percent_applied', 5, 2)->nullable();
            $table->unsignedInteger('cap_cents')->nullable();
            $table->string('waiver_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_fees');
    }
};
