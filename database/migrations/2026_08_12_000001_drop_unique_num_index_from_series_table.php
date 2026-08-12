<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('series', ['num'], 'unique')) {
            return;
        }

        Schema::table('series', static function (Blueprint $table): void {
            $table->dropUnique('series_num_unique');
        });
    }

    public function down(): void
    {
        Schema::table('series', static function (Blueprint $table): void {
            $table->unique('num');
        });
    }
};
