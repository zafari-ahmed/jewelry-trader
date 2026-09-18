<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rule 3.5: every state-changing action is logged, no role exempt.
 * Module 8 adds the retention job and the log viewer; the table is created
 * here because Module 1's acceptance requires settings changes to be audited.
 *
 * Retention note: financial records (payments, refunds, commissions) may need
 * a longer window than general activity — IRS recordkeeping is 7 years. The
 * category column exists so retention can be configured per category rather
 * than purging everything on one clock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 128);
            $table->string('category', 32)->default('general');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
