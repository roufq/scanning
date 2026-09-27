<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->char('screen_clock', 5)->nullable()->after('phash');
            $table->boolean('screen_clock_ambiguous')->default(false)->after('screen_clock');
            $table->json('change_regions')->nullable()->after('change_bbox');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropColumn(['screen_clock', 'screen_clock_ambiguous', 'change_regions']);
        });
    }
};
