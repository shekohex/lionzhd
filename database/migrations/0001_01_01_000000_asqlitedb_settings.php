<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('PRAGMA auto_vacuum = INCREMENTAL;');
            DB::unprepared('VACUUM;');
            DB::unprepared('PRAGMA journal_mode = DELETE;');
            DB::unprepared('PRAGMA page_size = 32768;');
            DB::unprepared('VACUUM;');
            DB::unprepared('PRAGMA journal_mode = WAL;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
