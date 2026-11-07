<?php

namespace Database\Factories\UserManagement;

use App\Models\UserManagement\GroupType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupType>
 */
class GroupTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 50),
        ];
    }

    /**
     * Indicate that the group type is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
