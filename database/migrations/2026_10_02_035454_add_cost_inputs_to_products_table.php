<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of the craftsman's formula: labour cost + material cost.
 *
 * Both live on the item record because both are properties of the piece, not
 * of a price list. Either may be left empty: labour then falls back to the
 * standard for its category, and materials to the rate table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('labor_cost_cents')->nullable()->after('weight_grams');
            $table->unsignedBigInteger('material_cost_cents')->nullable()->after('labor_cost_cents');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['labor_cost_cents', 'material_cost_cents']);
        });
    }
};
