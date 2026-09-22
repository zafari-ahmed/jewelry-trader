<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deliberately high-friction: a reason is required, approval is required, and
 * a Super Admin performing their own override goes through both.
 *
 * Types are a data column rather than a narrow enum so the list can grow from
 * the admin panel (rule 3.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('override_type', 64)->index();
            $table->text('reason');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('amount')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'applied'])->default('pending')->index();
            $table->text('decision_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overrides');
    }
};
