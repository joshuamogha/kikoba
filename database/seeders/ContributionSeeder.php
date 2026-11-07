<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContributionSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Monthly savings as a multiple of the minimum contribution, per member position.
     *
     * @var array<int, float>
     */
    private const SAVINGS_MULTIPLIERS = [3, 2, 4, 1, 2.5, 1, 1.5, 2, 1, 1.5, 2, 1];

    /**
     * Seed monthly contributions (savings + social fund) and share purchases
     * for the 2025 and 2026 cycles.
     */
    public function run(): void
    {
        mt_srand(2026);

        foreach (DB::table('groups')->orderBy('id')->get() as $groupIndex => $group) {
            $code = $this->groupCode($group);
            $settings = DB::table('group_settings')->where('group_id', $group->id)->first();
            $savingsFundId = $this->lookupId('group_funds', $group->id, 'code', 'SAV');
            $socialFund = DB::table('group_funds')->where('group_id', $group->id)->where('code', 'SOC')->first();
            $methodIds = $this->paymentMethodIds($group->id);
            $treasurerId = $this->leaderUserId($group->id, 'Treasurer');
            $chairId = $this->leaderUserId($group->id, 'Chairperson');

            $members = DB::table('group_members')->where('group_id', $group->id)->orderBy('id')->get();
            $meetingDay = 5 + 5 * $groupIndex;
            $contributionSequence = 0;
            $shareSequence = 0;

            foreach (DB::table('financial_cycles')->where('group_id', $group->id)->orderBy('cycle_number')->get() as $cycle) {
                $year = (int) substr($cycle->start_date, 0, 4);
                $lastMonth = $year === 2025 ? 12 : 10;

                for ($month = 1; $month <= $lastMonth; $month++) {
                    $periodStart = Carbon::create($year, $month, 1);
                    $contributionDate = $periodStart->copy()->day(min($meetingDay, $periodStart->daysInMonth));

                    foreach ($members as $index => $member) {
                        $joinedBeforeMonthEnds = Carbon::parse($member->joined_at)->lte($periodStart->copy()->endOfMonth());
                        $missedThisMonth = ($index * 7 + $month * 13 + ($year - 2000)) % 23 === 0;

                        if (! $joinedBeforeMonthEnds || $missedThisMonth) {
                            continue;
                        }

                        $savings = (int) (round($settings->minimum_contribution * self::SAVINGS_MULTIPLIERS[$index % 12] / 1000) * 1000);
                        $social = (int) $socialFund->minimum_contribution;
                        [$methodId, $reference] = $this->randomPayment($methodIds);

                        $contributionId = DB::table('contributions')->insertGetId($this->stamp([
                            'group_id' => $group->id,
                            'financial_cycle_id' => $cycle->id,
                            'group_member_id' => $member->id,
                            'contribution_number' => sprintf('%s-CTB-%d-%06d', $code, $year, ++$contributionSequence),
                            'contribution_date' => $contributionDate->toDateString(),
                            'total_amount' => $savings + $social,
                            'payment_method_id' => $methodId,
                            'external_reference' => $reference,
                            'status' => 'approved',
                            'notes' => null,
                            'received_by' => $treasurerId,
                            'approved_by' => $chairId,
                            'approved_at' => $contributionDate->copy()->addDay()->setTime(9, 0),
                        ]));

                        DB::table('contribution_items')->insert([
                            $this->stamp([
                                'contribution_id' => $contributionId,
                                'group_fund_id' => $savingsFundId,
                                'period_start' => $periodStart->toDateString(),
                                'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
                                'amount' => $savings,
                                'notes' => null,
                            ]),
                            $this->stamp([
                                'contribution_id' => $contributionId,
                                'group_fund_id' => $socialFund->id,
                                'period_start' => $periodStart->toDateString(),
                                'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
                                'amount' => $social,
                                'notes' => null,
                            ]),
                        ]);

                        // Members buy shares at the end of each quarter.
                        if ($month % 3 === 0) {
                            $shares = 1 + ($index + $month) % 3;

                            DB::table('member_share_transactions')->insert($this->stamp([
                                'group_id' => $group->id,
                                'financial_cycle_id' => $cycle->id,
                                'group_member_id' => $member->id,
                                'transaction_number' => sprintf('%s-SHR-%d-%06d', $code, $year, ++$shareSequence),
                                'transaction_type' => 'purchase',
                                'number_of_shares' => $shares,
                                'price_per_share' => $settings->share_price,
                                'total_amount' => $shares * $settings->share_price,
                                'transaction_date' => $contributionDate->toDateString(),
                                'status' => 'approved',
                                'reference' => $reference,
                                'approved_by' => $chairId,
                            ]));
                        }
                    }
                }
            }
        }
    }
}
