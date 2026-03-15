<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_category_preferences', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('media_type');
            $table->string('category_provider_id');
            $table->unsignedInteger('pin_rank')->nullable();
            $table->unsignedInteger('sort_order');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'media_type', 'category_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_category_preferences');
    }
};
