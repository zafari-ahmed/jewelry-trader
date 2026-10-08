<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The grading that actually moves a stone's value.
 *
 * The appraiser's rate table prices a diamond from its size, then adjusts for
 * clarity, colour and cut — a VS1 stone is worth 20% more than the SI
 * baseline, an I2 half as much. None of that could be recorded, so none of it
 * could be priced.
 *
 * Treatment is here for the same reason it is on every reputable appraisal:
 * heated, oiled and fracture-filled stones are worth materially less, and the
 * disclosure is not optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gemstone_details', function (Blueprint $table) {
            $table->string('clarity', 20)->nullable()->after('color');
            $table->string('cut_grade', 20)->nullable()->after('clarity');
            $table->string('treatment', 60)->nullable()->after('cut_grade');
            $table->string('quality_tier', 80)->nullable()->after('treatment');
        });
    }

    public function down(): void
    {
        Schema::table('gemstone_details', function (Blueprint $table) {
            $table->dropColumn(['clarity', 'cut_grade', 'treatment', 'quality_tier']);
        });
    }
};
