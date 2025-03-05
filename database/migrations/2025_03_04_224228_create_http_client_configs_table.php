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
        Schema::create('http_client_configs', function (Blueprint $table) {
            $table->id();
            $table->string('user_agent')->nullable()->default(env('HTTP_CLIENT_USER_AGENT'));
            $table->integer('timeout')->default(60);
            $table->integer('connect_timeout')->default(10);
            $table->boolean('verify_ssl')->default(true);
            $table->json('default_headers')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('http_client_configs');
    }
};
