<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The quality gate every piece passes through.
 *
 * Most of these rows are written by the system from data it already holds —
 * photographs taken, weight recorded, reviewer signed off — because asking
 * staff to tick fifty boxes a piece guarantees they tick them without
 * reading. The dozen that need a person's eyes are left pending until a
 * person actually looks.
 *
 * `verified_by` carries the attribution a customer-facing claim needs: the
 * public page shows the role and the date, the record keeps the name, and
 * the name is produced on request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_control_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 40)->index();
            $table->string('check_key', 60);
            $table->string('check_type', 12);              // critical, standard, optional
            $table->string('status', 20)->default('pending'); // passed, failed, pending, not_applicable
            $table->string('detail')->nullable();            // what the system saw
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            // A human changing what the system derived is an override, and
            // renders blue like every other override in this build.
            $table->boolean('is_override')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'check_key']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_control_checks');
    }
};
