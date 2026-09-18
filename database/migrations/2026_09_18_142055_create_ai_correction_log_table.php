<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The training-signal audit trail: what a human changed an AI suggestion to,
 * and why. Built in Phase 1 so the data exists from day one — Module 5 already
 * records overridden field names on products.manually_overridden_fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_correction_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('field_name', 128);
            $table->text('original_value')->nullable();
            $table->text('final_value')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_correction_log');
    }
};
