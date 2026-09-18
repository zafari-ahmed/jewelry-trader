<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Super-Admin-configurable value lives here (CLAUDE.md rule 3.1).
 * .env holds infrastructure secrets only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 128);
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->enum('type', ['string', 'integer', 'boolean', 'json', 'encrypted_string'])->default('string');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
