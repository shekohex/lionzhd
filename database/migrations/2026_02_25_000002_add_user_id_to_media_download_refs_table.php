<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_download_refs', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('media_download_refs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
