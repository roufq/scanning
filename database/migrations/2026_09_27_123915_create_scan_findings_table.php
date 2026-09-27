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
        Schema::create('scan_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screenshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_screenshot_id')->nullable()->constrained('screenshots')->nullOnDelete();
            $table->string('type');
            $table->decimal('score', 8, 4)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['scan_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_findings');
    }
};
