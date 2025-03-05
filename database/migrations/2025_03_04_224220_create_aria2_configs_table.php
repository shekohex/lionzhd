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
        Schema::create('aria2_configs', function (Blueprint $table) {
            $table->id();
            $table->string('host')->default(config('services.aria2.host'));
            $table->integer('port')->default(config('services.aria2.port'));
            $table->string('secret')->nullable()->default(config('services.aria2.secret'));
            $table->boolean('use_ssl')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aria2_configs');
    }
};
