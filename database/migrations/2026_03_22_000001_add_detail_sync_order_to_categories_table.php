<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', static function (Blueprint $table): void {
            $table->unsignedInteger('vod_sync_order')->nullable()->after('is_system');
            $table->unsignedInteger('series_sync_order')->nullable()->after('vod_sync_order');

            $table->index('vod_sync_order');
            $table->index('series_sync_order');
        });
    }

    public function down(): void
    {
        Schema::table('categories', static function (Blueprint $table): void {
            $table->dropIndex(['vod_sync_order']);
            $table->dropIndex(['series_sync_order']);
            $table->dropColumn(['vod_sync_order', 'series_sync_order']);
        });
    }
};
