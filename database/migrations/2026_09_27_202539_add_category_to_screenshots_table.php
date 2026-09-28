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
            $table->string('window_title', 300)->nullable()->after('screen_clock_ambiguous');
            $table->json('category_scores')->nullable()->after('window_title');
            $table->foreignId('category_id')->nullable()->after('category_scores')->constrained('scan_categories')->nullOnDelete();
            $table->string('category_source', 10)->nullable()->after('category_id');
            $table->decimal('category_confidence', 5, 4)->nullable()->after('category_source');
            $table->string('category_keyword')->nullable()->after('category_confidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screenshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['window_title', 'category_scores', 'category_source', 'category_confidence', 'category_keyword']);
        });
    }
};
