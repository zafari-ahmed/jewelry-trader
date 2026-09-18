<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors payment_gateways.driver_class: a provider row names the class that
 * implements it, so Phase 2 activation is a data row plus a class — never a
 * change to the resolver (rule 3.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->string('driver_class')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn('driver_class');
        });
    }
};
