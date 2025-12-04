<?php

declare(strict_types=1);

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
        Schema::create('categories', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('category_id');
            $table->string('category_name');
            $table->integer('parent_id')->default(0);
            $table->string('type'); // 'movie' or 'series'
            $table->timestamps();

            // Composite unique index to allow same ID for different types
            $table->unique(['category_id', 'type']);
        });

        if (Schema::hasTable('vod_streams')) {
            // Optional: DB::table('vod_streams')->truncate();
        }

        Schema::table('vod_streams', static function (Blueprint $table): void {
            $table->unsignedInteger('category_id')->nullable()->change();
            $table->index('category_id');
        });

        Schema::table('series', static function (Blueprint $table): void {
            $table->unsignedInteger('category_id')->nullable()->change();
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series', static function (Blueprint $table): void {
            $table->dropIndex(['category_id']);
            $table->string('category_id')->nullable()->change();
        });

        Schema::table('vod_streams', static function (Blueprint $table): void {
            $table->dropIndex(['category_id']);
            $table->string('category_id')->nullable()->change();
        });

        Schema::dropIfExists('categories');
    }
};
