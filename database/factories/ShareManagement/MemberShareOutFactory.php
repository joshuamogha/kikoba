<?php

namespace Database\Factories\ShareManagement;

use App\Models\ShareManagement\MemberShareOut;
use App\Models\ShareManagement\ShareOut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberShareOut>
 */
class MemberShareOutFactory extends Factory
{
    /**
     * Define the model's default state: owed 100,000 and not yet paid; pass group_member_id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'share_out_id' => ShareOut::factory(),
            'total_savings' => 100000,
            'net_payable' => 100000,
            'amount_paid' => 0,
            'status' => MemberShareOut::PENDING,
        ];
    }
}
