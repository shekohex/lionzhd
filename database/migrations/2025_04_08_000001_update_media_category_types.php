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
        // First convert the columns to unsigned integer (or appropriate type matching categories.id)
        // Since sqlite might complain about changing types with data, and we are truncating data on sync anyway,
        // it might be safer to drop and recreate or just modify.
        // Assuming MySQL/Postgres or SQLite with support for modification.
        // Since existing migrations create them as string, and we are likely in a fresh or sync-able state.

        // We will just modify the column.
        // Note: If there is existing data with non-integer strings, this might fail.
        // But the user said "We need to fetch all categories... create a relationship".
        // And the sync job truncates data anyway.

        Schema::table('series', static function (Blueprint $table): void {
             // In Laravel, modifying columns requires doctrine/dbal, which might not be installed.
             // However, since we can't run composer require, we hope it's there or we use raw SQL if needed.
             // But simpler approach for this task (given constraints):
             // We can drop the column and re-add it if we don't care about data preservation (sync truncates).
             // Or we simply rely on the fact that SQLite/MySQL allow altering.

             // Actually, the cleanest way without DBAL is to not use change() if possible,
             // but `change()` is the standard way.
             // If DBAL is missing, we might need a raw statement.
             // Let's try standard Laravel migration first.

             // Wait, the previous migrations created them as nullable strings.
             // We want them to be nullable integers.

             // If we are allowed to drop the column (since data is synced from API):
             $table->dropColumn('category_id');
        });

        Schema::table('series', static function (Blueprint $table): void {
            $table->unsignedInteger('category_id')->nullable()->after('episode_run_time');
            // We can add foreign key constraint if we want strictness, but API data might be messy.
            // $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });

        Schema::table('vod_streams', static function (Blueprint $table): void {
             $table->dropColumn('category_id');
        });

        Schema::table('vod_streams', static function (Blueprint $table): void {
            $table->unsignedInteger('category_id')->nullable()->after('is_adult');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series', static function (Blueprint $table): void {
            $table->dropColumn('category_id');
        });
        Schema::table('series', static function (Blueprint $table): void {
            $table->string('category_id')->nullable();
        });

        Schema::table('vod_streams', static function (Blueprint $table): void {
            $table->dropColumn('category_id');
        });
        Schema::table('vod_streams', static function (Blueprint $table): void {
            $table->string('category_id')->nullable();
        });
    }
};
