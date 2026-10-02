<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the reading service actually cost.
 *
 * A pilot is a measurement, not a leap of faith: before committing to a
 * supplier the business needs to see real spend against real catalogue
 * volume. One row per call, with the tokens the service reported and the cost
 * worked out from rates held in Settings — so a change of price list is a form
 * submission, and historic rows keep the cost they were charged at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_log', function (Blueprint $table) {
            $table->id();
            $table->string('capability', 40)->index();   // vision, description, search
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedSmallInteger('duration_ms')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('succeeded')->default(true);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_log');
    }
};
