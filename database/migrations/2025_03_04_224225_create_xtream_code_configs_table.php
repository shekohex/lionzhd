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
        Schema::create('xtream_code_configs', function (Blueprint $table) {
            $table->id();
            $table->string('host')->default(config('services.xtream.host'));
            $table->integer('port')->default(config('services.xtream.port'));
            $table->string('username')->default(config('services.xtream.username'));
            $table->string('password')->default(config('services.xtream.password'));
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xtream_code_configs');
    }
};
