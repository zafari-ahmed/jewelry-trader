<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proposed changes to the rate tables, awaiting a person.
 *
 * The AI proposes; the appraiser approves. Nothing reaches a shelf until a
 * human says yes — the same rule the rest of the system runs on, applied to
 * the one place where a single bad judgement would move every price at once.
 *
 * Proposals are kept after they are decided, approved or not. A rejected
 * proposal is evidence about where the readings are weak, which is worth more
 * than the row costs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_change_proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();

            // Which rate: the settings key holding the table, and the row in it.
            $table->string('table_key');
            $table->string('entry_key');

            // The value as it stood when the proposal was made. Kept so that a
            // human edit in the meantime can be noticed rather than clobbered.
            $table->decimal('value_at_proposal', 12, 4)->nullable();
            $table->decimal('proposed_value', 12, 4);

            $table->string('reason')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('source')->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();

            $table->timestamps();

            $table->index(['table_key', 'entry_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_change_proposals');
    }
};
