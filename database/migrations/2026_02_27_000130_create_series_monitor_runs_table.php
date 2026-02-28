<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_monitor_runs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitor_id')->constrained('series_monitors')->cascadeOnDelete();
            $table->string('trigger');
            $table->timestamp('window_start_at')->nullable();
            $table->timestamp('window_end_at')->nullable();
            $table->string('status');
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('deferred_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_monitor_runs');
    }
};
