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
        Schema::create('screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->dateTime('taken_at')->nullable();
            $table->string('taken_at_source');
            $table->char('md5', 32)->nullable()->index();
            $table->char('phash', 16)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->decimal('change_ratio', 8, 6)->nullable();
            $table->json('change_bbox')->nullable();
            $table->unsignedInteger('similarity_group')->nullable();
            $table->string('activity')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['scan_id', 'taken_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screenshots');
    }
};
