<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vod_streams', static function (Blueprint $table): void {
            $table->string('previous_category_id')->nullable()->after('category_id');
        });

        Schema::table('series', static function (Blueprint $table): void {
            $table->string('previous_category_id')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('vod_streams', static function (Blueprint $table): void {
            $table->dropColumn('previous_category_id');
        });

        Schema::table('series', static function (Blueprint $table): void {
            $table->dropColumn('previous_category_id');
        });
    }
};
