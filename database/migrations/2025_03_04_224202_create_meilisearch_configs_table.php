<?php

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
        Schema::create('meilisearch_configs', function (Blueprint $table) {
            $table->id();
            $table->string('api_endpoint')->default(config('scout.meilisearch.host'));
            $table->string('api_key')->default(config('scout.meilisearch.key'));
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meilisearch_configs');
    }
};
