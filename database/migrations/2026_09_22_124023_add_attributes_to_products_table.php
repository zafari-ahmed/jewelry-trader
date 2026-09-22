<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field rules are editable in Settings, so a Super Admin can introduce a field
 * name that has no column (ring size, chain length, movement type, or whatever
 * a new category needs). Those values live here rather than requiring a
 * migration per field — which would defeat the point of config-driven rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('attributes')->nullable()->after('measurements');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('attributes');
        });
    }
};
