<?php

namespace Database\Factories\PaymentManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\SettingManagement\ContributionFrequency;
use App\Models\UserManagement\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialCycle>
 */
class FinancialCycleFactory extends Factory
{
    /**
     * Define the model's default state: a closed 2025 cycle.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'contribution_frequency_id' => fn (array $attributes) => ContributionFrequency::query()->where('group_id', $attributes['group_id'])->value('id'),
            'name' => 'Mzunguko wa 2025',
            'cycle_number' => 1,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'closed',
        ];
    }
}
