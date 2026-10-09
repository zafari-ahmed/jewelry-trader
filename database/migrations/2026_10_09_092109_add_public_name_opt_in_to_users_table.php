<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a staff member's name may appear on the public page.
 *
 * Default off. Publishing a named individual's professional judgement on a
 * commercial page attached to a high-value sale is their decision, not the
 * shop's — the page shows their role and the date unless they opt in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_name_publicly')->default(false)->after('is_active');
            $table->string('job_title')->nullable()->after('show_name_publicly');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['show_name_publicly', 'job_title']);
        });
    }
};
