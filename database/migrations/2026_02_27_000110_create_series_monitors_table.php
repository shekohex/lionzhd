<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_monitors', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('series_id');
            $table->foreignId('watchlist_id')->nullable()->constrained('watchlists')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('timezone');
            $table->string('schedule_type');
            $table->string('schedule_daily_time')->nullable();
            $table->json('schedule_weekly_days')->nullable();
            $table->string('schedule_weekly_time')->nullable();
            $table->json('monitored_seasons')->default(json_encode([]));
            $table->unsignedInteger('per_run_cap')->default(5);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_attempt_status')->nullable();
            $table->timestamp('last_successful_check_at')->nullable();
            $table->timestamp('run_now_available_at')->nullable();
            $table->timestamps();

            $table->foreign('series_id')->references('series_id')->on('series')->cascadeOnDelete();
            $table->unique(['user_id', 'series_id']);
            $table->index(['enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_monitors');
    }
};
