<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserSubtype;
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
        Schema::table('users', static function (Blueprint $table): void {
            $table->string('role')->default(UserRole::Member->value);
            $table->string('subtype')->default(UserSubtype::External->value);
            $table->boolean('is_super_admin')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn(['role', 'subtype', 'is_super_admin']);
        });
    }
};
