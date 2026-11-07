<?php

namespace Database\Factories\ShareManagement;

use App\Models\PaymentManagement\FinancialCycle;
use App\Models\ShareManagement\ShareOut;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShareOut>
 */
class ShareOutFactory extends Factory
{
    /**
     * Define the model's default state: an empty draft share out.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'financial_cycle_id' => fn (array $attributes) => FinancialCycle::factory()->create(['group_id' => $attributes['group_id']])->id,
            'share_out_number' => 'SO-'.fake()->unique()->numerify('#####'),
            'calculation_date' => '2025-12-20',
            'status' => ShareOut::DRAFT,
            'calculated_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ShareOut::APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
