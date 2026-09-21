<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gemstone_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('stone_type')->nullable();
            $table->string('shape')->nullable();
            $table->string('cut')->nullable();
            $table->string('color')->nullable();
            $table->decimal('estimated_weight_ct', 8, 3)->nullable();
            $table->string('setting_style')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gemstone_details');
    }
};
