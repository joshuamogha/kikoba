<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupSetupSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed settings, funds, financial cycles, fine types, loan types and loan products.
     * Amounts are in Tanzanian shillings (TZS).
     */
    public function run(): void
    {
        $adminId = DB::table('users')->where('email', 'admin@kibubu.co.tz')->value('id');

        // Scale differs between the village VICOBA and the larger SACCOS.
        $profiles = [
            'KND' => [
                'min_contribution' => 20000, 'max_contribution' => 100000, 'social' => 5000, 'share_price' => 5000,
                'max_shares' => 40, 'loan_cap' => [500000, 1000000, 600000], 'development_months' => 12,
                'fines' => [2000, 1000, 2000],
            ],
            'ARU' => [
                'min_contribution' => 50000, 'max_contribution' => 150000, 'social' => 10000, 'share_price' => 10000,
                'max_shares' => 60, 'loan_cap' => [1500000, 5000000, 2000000], 'development_months' => 24,
                'fines' => [5000, 2000, 5000],
            ],
        ];

        foreach (DB::table('groups')->orderBy('id')->get() as $group) {
            $profile = $profiles[$this->groupCode($group)];
            $monthlyId = $this->lookupId('contribution_frequencies', $group->id, 'name', 'Monthly (Kila mwezi)');
            $flatId = $this->lookupId('loan_interest_methods', $group->id, 'code', 'FLAT');
            $monthlyRepaymentId = $this->lookupId('repayment_frequencies', $group->id, 'name', 'Monthly');
            $chairId = $this->leaderUserId($group->id, 'Chairperson');
            $secretaryId = $this->leaderUserId($group->id, 'Secretary');

            DB::table('group_settings')->insert($this->stamp([
                'group_id' => $group->id,
                'contribution_frequency_id' => $monthlyId,
                'loan_interest_method_id' => $flatId,
                'penalty_type_id' => $this->lookupId('penalty_types', $group->id, 'name', 'Late Contribution'),
                'minimum_contribution' => $profile['min_contribution'],
                'maximum_contribution' => $profile['max_contribution'],
                'share_price' => $profile['share_price'],
                'minimum_shares' => 1,
                'maximum_shares' => $profile['max_shares'],
                'default_loan_interest_rate' => 3,
                'maximum_loan_multiplier' => 3,
                'default_loan_duration_months' => 6,
                'loan_grace_period_days' => 7,
                'late_payment_penalty_value' => 5,
                'loan_guarantors_required' => 2,
                'withdrawal_approvals_required' => 2,
                'allow_partial_contributions' => false,
                'allow_multiple_loans' => false,
            ]));

            $funds = [
                ['SAV', 'Akiba (Savings)', 'Mandatory monthly member savings.', $profile['min_contribution'], true, true, true],
                ['SHR', 'Hisa (Shares)', 'Share capital bought by members.', 0, false, true, false],
                ['SOC', 'Mfuko wa Jamii (Social Fund)', 'Welfare fund for funerals, weddings and emergencies.', $profile['social'], true, false, false],
                ['GEN', 'Mfuko Mkuu (General Fund)', 'Operating income and expenses of the group.', 0, false, true, false],
            ];

            foreach ($funds as [$code, $name, $description, $minimum, $mandatory, $distributable, $loanable]) {
                DB::table('group_funds')->insert($this->stamp([
                    'group_id' => $group->id,
                    'fund_type_id' => $this->lookupId('fund_types', $group->id, 'code', $code),
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'minimum_contribution' => $minimum,
                    'contribution_frequency_id' => $mandatory ? $monthlyId : null,
                    'is_mandatory' => $mandatory,
                    'is_distributable' => $distributable,
                    'is_loanable' => $loanable,
                    'status' => 'active',
                    'created_by' => $adminId,
                ]));
            }

            DB::table('financial_cycles')->insert([
                $this->stamp([
                    'group_id' => $group->id,
                    'contribution_frequency_id' => $monthlyId,
                    'name' => 'Mzunguko wa 2025',
                    'cycle_number' => 1,
                    'start_date' => '2025-01-01',
                    'end_date' => '2025-12-31',
                    'status' => 'closed',
                    'opened_by' => $chairId,
                    'closed_by' => $chairId,
                    'closed_at' => '2025-12-28 16:00:00',
                    'notes' => 'Mgao wa mwisho wa mwaka umekamilika.',
                ]),
                $this->stamp([
                    'group_id' => $group->id,
                    'contribution_frequency_id' => $monthlyId,
                    'name' => 'Mzunguko wa 2026',
                    'cycle_number' => 2,
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'status' => 'active',
                    'opened_by' => $secretaryId,
                    'closed_by' => null,
                    'closed_at' => null,
                    'notes' => null,
                ]),
            ]);

            $fineTypes = [
                ['MISSED_CONTRIB', 'Kukosa mchango', 'Fine for not paying the monthly contribution.', $profile['fines'][0], 'fixed'],
                ['LATE_MEETING', 'Kuchelewa kikao', 'Fine for arriving late to a meeting.', $profile['fines'][1], 'fixed'],
                ['ABSENT_MEETING', 'Kukosa kikao', 'Fine for missing a meeting without permission.', $profile['fines'][2], 'fixed'],
                ['LATE_LOAN', 'Kuchelewa kurejesha mkopo', 'Percentage of the installment charged when it is overdue.', 5, 'percentage'],
            ];

            foreach ($fineTypes as [$code, $name, $description, $amount, $type]) {
                DB::table('fine_types')->insert($this->stamp([
                    'group_id' => $group->id,
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'default_amount' => $amount,
                    'calculation_type' => $type,
                    'status' => 'active',
                ]));
            }

            foreach (['Emergency (Dharura)', 'Development (Maendeleo)', 'Education (Elimu)'] as $index => $name) {
                DB::table('loan_types')->insert($this->stamp([
                    'group_id' => $group->id,
                    'name' => $name,
                    'description' => null,
                    'display_order' => $index + 1,
                    'is_active' => true,
                ]));
            }

            $savingsFundId = $this->lookupId('group_funds', $group->id, 'code', 'SAV');

            // [code, name, rate % per month, min months, max months, cap, min amount]
            $products = [
                ['DHARURA', 'Mkopo wa Dharura', 5, 1, 3, $profile['loan_cap'][0], $profile['min_contribution'] * 2],
                ['MAENDELEO', 'Mkopo wa Maendeleo', 3, 3, $profile['development_months'], $profile['loan_cap'][1], $profile['min_contribution'] * 5],
                ['ELIMU', 'Mkopo wa Elimu', 2, 2, 12, $profile['loan_cap'][2], $profile['min_contribution'] * 5],
            ];

            foreach ($products as [$code, $name, $rate, $minMonths, $maxMonths, $cap, $minAmount]) {
                DB::table('loan_products')->insert($this->stamp([
                    'group_id' => $group->id,
                    'group_fund_id' => $savingsFundId,
                    'repayment_frequency_id' => $monthlyRepaymentId,
                    'loan_interest_method_id' => $flatId,
                    'name' => $name,
                    'code' => $code,
                    'description' => null,
                    'minimum_amount' => $minAmount,
                    'maximum_amount' => $cap,
                    'maximum_savings_multiplier' => 3,
                    'interest_rate' => $rate,
                    'interest_period' => 'per_month',
                    'minimum_duration_months' => $minMonths,
                    'maximum_duration_months' => $maxMonths,
                    'grace_period_days' => 7,
                    'guarantors_required' => 2,
                    'status' => 'active',
                    'created_by' => $adminId,
                ]));
            }
        }
    }
}
