<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            // Tax is US state-based, so the state drives the rate for POS sales here.
            $table->string('state', 2)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->decimal('tax_rate', 6, 4)->default(0);
            $table->string('phone', 32)->nullable();
            $table->string('timezone', 64)->default('America/Los_Angeles');
            $table->boolean('is_active')->default(true);
            // Web orders belong to a dedicated "Web" location (docs/DECISIONS.md).
            $table->boolean('is_web')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
