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
        Schema::create('http_client_configs', static function (Blueprint $table): void {
            $table->id();
            $table->string('user_agent');
            $table->integer('timeout');
            $table->integer('connect_timeout');
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
