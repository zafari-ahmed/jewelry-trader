<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a stone came from, which is not the same question as what was done
 * to it.
 *
 * Lab-grown was being recorded as a treatment, in the list beside heated and
 * oiled. It is an origin, and the distinction is not academic: a lab-grown
 * ruby can also be heated, and a single field holding both loses whichever
 * was entered second. The one that gets lost is the one the FTC requires us
 * to disclose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gemstone_details', function (Blueprint $table) {
            $table->string('origin', 40)->nullable()->after('stone_type');
        });

        // Anything already recorded as grown or synthetic is an origin that
        // was filed in the wrong place. Move it, and leave the treatment
        // field free for what was actually done to the stone.
        foreach (['lab grown', 'lab-grown', 'synthetic'] as $value) {
            DB::table('gemstone_details')
                ->whereRaw('LOWER(treatment) = ?', [$value])
                ->update(['origin' => 'Lab-Grown', 'treatment' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('gemstone_details', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
