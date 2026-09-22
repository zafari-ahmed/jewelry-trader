<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lock types are the spec's five *effects*; the design's names (valuation
 * hold, repair, consignment dispute, legal hold) are free text in the reason
 * (docs/DECISIONS.md). "rental" exists now so Phase 2 needs no migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('lock_type', ['full', 'sales', 'rental', 'edit', 'view']);
            $table->text('reason');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->useCurrent();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unlocked_at')->nullable();

            $table->index(['product_id', 'unlocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locks');
    }
};
