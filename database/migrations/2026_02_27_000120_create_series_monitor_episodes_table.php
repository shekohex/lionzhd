<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_monitor_episodes', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitor_id')->constrained('series_monitors')->cascadeOnDelete();
            $table->string('episode_id');
            $table->unsignedInteger('season');
            $table->unsignedInteger('episode_num');
            $table->string('state');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_queued_at')->nullable();
            $table->foreignId('last_download_ref_id')->nullable()->constrained('media_download_refs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['monitor_id', 'episode_id']);
            $table->index(['monitor_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_monitor_episodes');
    }
};
