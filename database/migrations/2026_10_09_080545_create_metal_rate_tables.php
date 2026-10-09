<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layer 2 — the live metals feed, as data rather than as code.
 *
 * Two tables. The providers are rows so that adding a feed is a record and a
 * form, never a deploy (rule 3.1) — the same pattern as payment gateways.
 * The fetch log is what makes the feed accountable: every call, what came
 * back, how long it took, and what went wrong when it did. Without it,
 * "the rates look odd today" has no answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_rate_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('endpoint')->nullable();
            // Where the rates sit in the response, and the unit they are
            // quoted in: the two things that differ between feeds.
            $table->string('rates_path')->nullable();
            $table->boolean('quoted_per_ounce')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('metal_rate_fetches', function (Blueprint $table) {
            $table->id();
            $table->string('provider_slug')->nullable();
            $table->boolean('succeeded')->default(false);
            // The rates as fetched, per gram of fine metal. The most recent
            // successful row is also the "last known rate" fallback.
            $table->json('rates')->nullable();
            $table->string('error')->nullable();
            $table->unsignedSmallInteger('duration_ms')->nullable();
            $table->boolean('was_test')->default(false);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_rate_fetches');
        Schema::dropIfExists('metal_rate_providers');
    }
};
