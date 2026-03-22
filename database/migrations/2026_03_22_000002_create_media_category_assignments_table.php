<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_category_assignments', static function (Blueprint $table): void {
            $table->id();
            $table->string('media_type', 20);
            $table->string('media_provider_id');
            $table->string('category_provider_id');
            $table->unsignedInteger('source_order');
            $table->timestamps();

            $table->unique(['media_type', 'media_provider_id', 'category_provider_id'], 'media_category_assignments_unique');
            $table->index(['media_type', 'media_provider_id', 'source_order'], 'media_category_assignments_lookup');
            $table->index('category_provider_id');
        });

        $this->backfillAssignments();
    }

    public function down(): void
    {
        Schema::dropIfExists('media_category_assignments');
    }

    private function backfillAssignments(): void
    {
        $now = now();

        $vodRows = DB::table('vod_streams')
            ->whereNotNull('category_id')
            ->where('category_id', '!=', '')
            ->orderBy('stream_id')
            ->get(['stream_id', 'category_id']);

        $vodAssignments = [];

        foreach ($vodRows as $row) {
            $vodAssignments[] = [
                'media_type' => 'vod',
                'media_provider_id' => (string) $row->stream_id,
                'category_provider_id' => $row->category_id,
                'source_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($vodAssignments !== []) {
            DB::table('media_category_assignments')->insert($vodAssignments);
        }

        $seriesRows = DB::table('series')
            ->whereNotNull('category_id')
            ->where('category_id', '!=', '')
            ->orderBy('series_id')
            ->get(['series_id', 'category_id']);

        $seriesAssignments = [];

        foreach ($seriesRows as $row) {
            $seriesAssignments[] = [
                'media_type' => 'series',
                'media_provider_id' => (string) $row->series_id,
                'category_provider_id' => $row->category_id,
                'source_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($seriesAssignments !== []) {
            DB::table('media_category_assignments')->insert($seriesAssignments);
        }
    }
};
