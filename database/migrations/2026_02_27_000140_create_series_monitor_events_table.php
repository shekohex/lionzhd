<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_monitor_events', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('series_monitor_runs')->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained('series_monitors')->cascadeOnDelete();
            $table->string('episode_id')->nullable();
            $table->unsignedInteger('season')->nullable();
            $table->unsignedInteger('episode_num')->nullable();
            $table->string('type');
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_monitor_events');
    }
};
