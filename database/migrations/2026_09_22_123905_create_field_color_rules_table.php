<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The colour-coded field system is configuration, not code (rule 3.1).
 *
 * A rule with a null category is the default for every item; a rule naming a
 * category overrides it. Editing a rule in Settings takes effect on the next
 * request — no deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_color_rules', function (Blueprint $table) {
            $table->id();
            $table->string('model')->default('App\\Models\\Product');
            $table->string('field_name', 128);
            // Null = applies to every category; a slug overrides the default.
            $table->string('category')->nullable();
            $table->enum('color', ['red', 'yellow', 'green', 'gray', 'blue'])->default('green');
            $table->boolean('is_required')->default(false);
            $table->string('section', 64)->default('details');
            $table->string('label')->nullable();
            $table->string('help')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['model', 'field_name', 'category']);
            $table->index(['model', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_color_rules');
    }
};
