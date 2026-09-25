<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which fields currently hold an unverified AI suggestion.
 *
 * This is what makes the colour system meaningful: a suggested value shows
 * yellow until a person accepts it (green) or replaces it (blue). Without it,
 * a suggestion would be indistinguishable from a human's own answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('ai_suggested_fields')->nullable()->after('manually_overridden_fields');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('ai_suggested_fields');
        });
    }
};
