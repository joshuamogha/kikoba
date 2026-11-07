<?php

namespace Database\Factories\UserManagement;

use App\Models\UserManagement\Relation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Relation>
 */
class RelationFactory extends Factory
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
            'display_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the relation is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
