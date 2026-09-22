<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The requirements call this "hieroglyphic security". It is implemented as a
 * configurable second-factor step-up: a shared-knowledge test whose symbol set
 * and covered actions are both configurable.
 *
 * The security comes from the answer being secret, not from the symbols being
 * hieroglyphs — so answers are hashed, and the whole mechanism can be swapped
 * for TOTP step-up by changing security.stepup_method.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_up_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('symbol_path')->nullable();
            $table->string('symbol_text', 16)->nullable();
            $table->string('answer_hash');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_up_challenges');
    }
};
