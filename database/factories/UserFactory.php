<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserSubtype;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    private static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'role' => UserRole::Member,
            'subtype' => UserSubtype::External,
            'is_super_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'is_super_admin' => false,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->admin()->state(fn (array $attributes) => [
            'is_super_admin' => true,
        ]);
    }

    public function memberInternal(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Member,
            'subtype' => UserSubtype::Internal,
            'is_super_admin' => false,
        ]);
    }

    public function memberExternal(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Member,
            'subtype' => UserSubtype::External,
            'is_super_admin' => false,
        ]);
    }
}
