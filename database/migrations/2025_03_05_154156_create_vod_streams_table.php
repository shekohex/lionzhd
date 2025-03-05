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
        Schema::create('vod_streams', function (Blueprint $table) {
            $table->unsignedInteger('num')->primary();
            $table->string('name');
            $table->string('stream_type');
            $table->unsignedInteger('stream_id')->unique();
            $table->string('stream_icon')->nullable();
            $table->string('rating')->nullable();
            $table->decimal('rating_5based', 3, 1)->default(0);
            $table->string('added');
            $table->boolean('is_adult')->default(false);
            $table->string('category_id');
            $table->string('container_extension');
            $table->string('custom_sid')->nullable();
            $table->string('direct_source')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vod_streams');
    }
};
