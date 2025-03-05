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
        Schema::create('series', function (Blueprint $table) {
            $table->integer('num')->primary();
            $table->string('name');
            $table->integer('series_id')->unique();
            $table->string('cover')->nullable();
            $table->text('plot')->nullable();
            $table->text('cast')->nullable();
            $table->string('director')->nullable();
            $table->string('genre')->nullable();
            $table->date('releaseDate')->nullable();
            $table->string('last_modified')->nullable();
            $table->string('rating')->nullable();
            $table->decimal('rating_5based', 3, 1)->nullable();
            $table->json('backdrop_path')->nullable();
            $table->string('youtube_trailer')->nullable();
            $table->string('episode_run_time')->nullable();
            $table->string('category_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
