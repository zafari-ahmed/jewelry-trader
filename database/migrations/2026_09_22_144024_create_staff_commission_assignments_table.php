<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * California Labor Code §2751 requires a **written** commission agreement.
 * terms_text (or an uploaded document) stores what the employee actually
 * agreed to, alongside the numeric plan — the plan alone is not the agreement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_commission_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_plan_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('terms_text')->nullable();
            $table->string('terms_document_path')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_commission_assignments');
    }
};
