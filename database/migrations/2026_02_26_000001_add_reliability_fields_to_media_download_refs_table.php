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
            $table->boolean('desired_paused')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->boolean('cancel_delete_partial')->default(false);
            $table->integer('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedInteger('retry_attempt')->default(0);
            $table->timestamp('retry_next_at')->nullable()->index();
            $table->json('download_files')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_download_refs', function (Blueprint $table): void {
            $table->dropIndex(['retry_next_at']);
            $table->dropColumn([
                'desired_paused',
                'canceled_at',
                'cancel_delete_partial',
                'last_error_code',
                'last_error_message',
                'retry_attempt',
                'retry_next_at',
                'download_files',
            ]);
        });
    }
};
