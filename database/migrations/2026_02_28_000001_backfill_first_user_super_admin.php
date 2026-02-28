<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('users')->where('is_super_admin', true)->exists()) {
            return;
        }

        $firstUserId = DB::table('users')
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        if ($firstUserId === null) {
            return;
        }

        DB::table('users')
            ->where('id', $firstUserId)
            ->update([
                'role' => UserRole::Admin->value,
                'is_super_admin' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
