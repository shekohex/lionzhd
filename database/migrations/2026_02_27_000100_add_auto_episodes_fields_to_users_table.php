<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->timestamp('auto_episodes_paused_at')->nullable();
            $table->timestamp('auto_episodes_last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn([
                'auto_episodes_paused_at',
                'auto_episodes_last_seen_at',
            ]);
        });
    }
};
