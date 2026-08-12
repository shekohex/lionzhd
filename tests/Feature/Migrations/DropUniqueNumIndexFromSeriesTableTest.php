<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('allows provider series numbers to change without unique constraint failures', function (): void {
    Schema::table('series', static function (Blueprint $table): void {
        $table->unique('num');
    });

    $migration = require database_path('migrations/2026_08_12_000001_drop_unique_num_index_from_series_table.php');
    $migration->up();

    expect(Schema::hasIndex('series', ['num'], 'unique'))->toBeFalse();
});

it('is safe when the unique index was already removed', function (): void {
    $migration = require database_path('migrations/2026_08_12_000001_drop_unique_num_index_from_series_table.php');

    $migration->up();

    expect(Schema::hasIndex('series', ['num'], 'unique'))->toBeFalse();
});

it('keeps rollback safe after duplicate provider numbers are stored', function (): void {
    $migration = require database_path('migrations/2026_08_12_000001_drop_unique_num_index_from_series_table.php');

    DB::table('series')->insert([
        ['series_id' => 70_001, 'num' => 10, 'name' => 'First Duplicate'],
        ['series_id' => 70_002, 'num' => 10, 'name' => 'Second Duplicate'],
    ]);

    $migration->down();

    expect(Schema::hasIndex('series', ['num'], 'unique'))->toBeFalse()
        ->and(DB::table('series')->where('num', 10)->count())->toBe(2);
});
