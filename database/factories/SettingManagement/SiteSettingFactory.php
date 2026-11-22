<?php

namespace Database\Factories\SettingManagement;

use App\Models\SettingManagement\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSetting>
 */
class SiteSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'motto' => fake()->sentence(4),
            'support_email' => fake()->safeEmail(),
            'support_phone' => '+255712345678',
            'brand_primary' => '#0d6efd',
            'seo_title' => fake()->sentence(5),
            'seo_description' => fake()->sentence(12),
        ];
    }
}
