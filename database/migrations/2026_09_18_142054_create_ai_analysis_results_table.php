<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unused until Phase 2. Created now so activating AI is implementing behind a
 * seam, not re-architecting (CLAUDE.md §6).
 *
 * product_id has no foreign key yet — products arrives in Module 4, which adds
 * the constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('analysis_type', 32);
            $table->json('raw_response')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->text('reasoning_summary')->nullable();
            $table->string('model_used')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'analysis_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_results');
    }
};
