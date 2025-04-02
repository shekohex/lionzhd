<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_download_refs', function (Blueprint $table): void {
            $table->id();
            $table->string('gid')->unique();
            $table->morphs('media'); // Polymorphic relationship to both VODs and Series
            $table->unsignedInteger('downloadable_id');
            $table->unsignedInteger('episode')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_download_refs');
    }
};
