<?php

namespace Database\Seeders;

use App\Actions\ShareManagement\CalculateShareOut;
use App\Models\PaymentManagement\FinancialCycle;
use App\Models\ShareManagement\MemberShareOut;
use App\Models\ShareManagement\ShareOut;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShareOutSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed the year-end share-out (mgao) of the closed 2025 cycle and pay it out.
     */
    public function run(): void
    {
        mt_srand(2030);

        foreach (Group::query()->orderBy('id')->get() as $group) {
            $code = $this->groupCode($group);
            $cycle = FinancialCycle::query()->where('group_id', $group->id)->where('cycle_number', 1)->firstOrFail();
            $methodIds = $this->paymentMethodIds($group->id);
            $chairId = $this->leaderUserId($group->id, 'Chairperson');

            // The same calculation a treasurer runs from the application.
            $shareOut = app(CalculateShareOut::class)->handle(
                $cycle,
                User::query()->findOrFail($this->leaderUserId($group->id, 'Treasurer')),
                '2025-12-20',
            );

            $shareOut->update([
                'share_out_number' => sprintf('%s-SO-2025-001', $code),
                'status' => ShareOut::COMPLETED,
                'approved_by' => $chairId,
                'approved_at' => '2025-12-22 14:00:00',
                'completed_at' => '2025-12-28 16:00:00',
            ]);

            $paymentSequence = 0;

            foreach ($shareOut->memberShareOuts()->orderBy('id')->get() as $row) {
                $row->update([
                    'amount_paid' => $row->net_payable,
                    'status' => MemberShareOut::PAID,
                    'paid_at' => '2025-12-27 11:00:00',
                ]);

                if ((float) $row->net_payable <= 0) {
                    continue;
                }

                [$methodId, $reference] = $this->randomPayment($methodIds);

                DB::table('share_out_payments')->insert($this->stamp([
                    'member_share_out_id' => $row->id,
                    'payment_number' => sprintf('%s-SOP-2025-%04d', $code, ++$paymentSequence),
                    'payment_date' => '2025-12-27',
                    'amount' => $row->net_payable,
                    'payment_method_id' => $methodId,
                    'external_reference' => $reference,
                    'status' => 'paid',
                    'approved_by' => $chairId,
                ]));
            }
        }
    }
}
