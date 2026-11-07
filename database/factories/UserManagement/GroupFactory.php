<?php

namespace Database\Factories\UserManagement;

use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupType;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_type_id' => GroupType::factory(),
            'name' => fake()->unique()->company().' Group',
            'description' => fake()->sentence(),
            'registration_number' => null,
            'established_date' => fake()->date(),
            'currency' => 'TZS',
            'maximum_members' => null,
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
