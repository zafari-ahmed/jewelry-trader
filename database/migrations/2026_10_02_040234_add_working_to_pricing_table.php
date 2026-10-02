<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The arithmetic that produced a price, kept with the price.
 *
 * A figure in a history row tells you what was charged; it does not tell you
 * why. This holds the formula's steps and the layers applied on top, as they
 * stood at the moment the price was set — so a price set two years ago can
 * still be explained even after every rate in the table has moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing', function (Blueprint $table) {
            $table->json('working')->nullable()->after('promo_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('pricing', function (Blueprint $table) {
            $table->dropColumn('working');
        });
    }
};
