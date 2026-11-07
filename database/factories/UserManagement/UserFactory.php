<?php

namespace Database\Factories\UserManagement;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

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
            'phone_number' => '07'.fake()->unique()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
            'is_active' => true,
            'is_platform_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user administers the whole platform.
     */
    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_platform_admin' => true,
        ]);
    }

    /**
     * Indicate that the account is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
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
}
