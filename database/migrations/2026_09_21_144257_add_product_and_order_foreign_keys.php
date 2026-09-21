<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the constraints Modules 2 and 3 left open: those tables were created
 * before products and orders existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('ai_analysis_results', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('ai_correction_log', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropForeign(['order_id']));
        Schema::table('ai_analysis_results', fn (Blueprint $table) => $table->dropForeign(['product_id']));
        Schema::table('ai_correction_log', fn (Blueprint $table) => $table->dropForeign(['product_id']));
    }
};
