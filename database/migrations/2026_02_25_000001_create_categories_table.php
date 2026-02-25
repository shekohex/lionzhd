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
        Schema::create('categories', static function (Blueprint $table): void {
            $table->id();
            $table->string('provider_id')->unique();
            $table->string('name');
            $table->boolean('in_vod');
            $table->boolean('in_series');
            $table->boolean('is_system');
            $table->timestamps();

            $table->index('in_vod');
            $table->index('in_series');
        });

        $now = now();

        DB::table('categories')->insert([
            [
                'provider_id' => '__uncategorized_vod__',
                'name' => 'Uncategorized',
                'in_vod' => true,
                'in_series' => false,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'provider_id' => '__uncategorized_series__',
                'name' => 'Uncategorized',
                'in_vod' => false,
                'in_series' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
