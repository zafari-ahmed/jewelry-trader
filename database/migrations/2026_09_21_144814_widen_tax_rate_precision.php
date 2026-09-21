<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * decimal(6,4) cannot hold a real US sales-tax rate: New York City's 8.875%
 * is 0.08875, which rounds to 0.0888 and overcharges every sale by about
 * $0.03 per $100. Six decimal places covers every US state and local rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('tax_rate', 9, 6)->default(0)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tax_rate', 9, 6)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('locations', fn (Blueprint $table) => $table->decimal('tax_rate', 6, 4)->default(0)->change());
        Schema::table('orders', fn (Blueprint $table) => $table->decimal('tax_rate', 6, 4)->default(0)->change());
    }
};
