<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Quarterly Audit. Tables only: no business logic, gated by
 * features.audit.quarterly_enabled (CLAUDE.md Module 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_reports', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('type', 64)->default('quarterly');
            $table->enum('status', ['draft', 'in_progress', 'complete'])->default('draft');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->enum('status', ['open', 'investigating', 'resolved'])->default('open');
            $table->foreignId('investigated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_exceptions');
        Schema::dropIfExists('audit_reports');
    }
};
