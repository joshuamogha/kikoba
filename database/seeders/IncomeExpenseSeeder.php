<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncomeExpenseSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed group incomes and expenses for the 2025 and 2026 cycles.
     */
    public function run(): void
    {
        mt_srand(2029);

        foreach (DB::table('groups')->orderBy('id')->get() as $group) {
            $code = $this->groupCode($group);
            $profile = $this->profiles()[$code];
            $methodIds = $this->paymentMethodIds($group->id);
            $generalFundId = $this->lookupId('group_funds', $group->id, 'code', 'GEN');
            $socialFundId = $this->lookupId('group_funds', $group->id, 'code', 'SOC');
            $treasurer = DB::table('group_members')
                ->join('users', 'users.id', '=', 'group_members.user_id')
                ->where('group_members.group_id', $group->id)
                ->where('users.id', $this->leaderUserId($group->id, 'Treasurer'))
                ->first(['users.id', 'users.name']);
            $chairId = $this->leaderUserId($group->id, 'Chairperson');
            $memberCount2025 = DB::table('group_members')->where('group_id', $group->id)->where('joined_at', '<', '2025-01-01')->count();
            $cycles = DB::table('financial_cycles')->where('group_id', $group->id)->get()
                ->keyBy(fn ($cycle) => (int) substr($cycle->start_date, 0, 4));

            $incomes = [];
            $expenses = [];

            foreach ([2025, 2026] as $year) {
                $lastMonth = $year === 2025 ? 12 : 10;

                $incomes[] = [$year, 'Passbook Sales', "$year-01-11", 'Mauzo ya vitabu vya akiba kwa wanachama.', $memberCount2025 * 2000, 'CASH'];

                foreach ([3, 6, 9, 12] as $month) {
                    if ($month <= $lastMonth) {
                        $incomes[] = [$year, 'Bank Interest', sprintf('%d-%02d-28', $year, $month), 'Riba ya akaunti ya kikundi - '.$profile['bank'].'.', $profile['bank_interest'] + $month * 120, 'BANK'];
                    }
                }

                for ($month = 1; $month <= $lastMonth; $month++) {
                    $expenses[] = [$year, 'Bank Charges', sprintf('%d-%02d-28', $year, $month), 'Makato ya akaunti ya benki ya kikundi.', $profile['bank_fee'], $profile['bank'], 'BANK'];
                    $expenses[] = [$year, 'Meeting Venue', sprintf('%d-%02d-%02d', $year, $month, $profile['meeting_day']), 'Kodi ya ukumbi wa kikao cha mwezi.', $profile['venue_fee'], $profile['venue'], 'CASH'];
                }

                foreach ([3, 9] as $month) {
                    $expenses[] = [$year, 'Stationery', sprintf('%d-%02d-15', $year, $month), 'Vitabu vya kumbukumbu, kalamu na risiti.', $profile['stationery'], $profile['stationers'], 'CASH'];
                }

                foreach ([3, 6, 9, 12] as $month) {
                    if ($month <= $lastMonth) {
                        $expenses[] = [$year, 'Transport', sprintf('%d-%02d-26', $year, $month), 'Nauli ya mweka hazina kupeleka fedha benki.', $profile['transport'], $treasurer->name, 'MPESA'];
                    }
                }
            }

            $incomes[] = [2025, 'Donations & Grants', $profile['grant_2025_date'], $profile['grant_2025_text'], $profile['grant_2025'], 'BANK'];
            $incomes[] = [2026, 'Registration Fees', $profile['new_member_date'], 'Ada ya kujiunga - mwanachama mpya.', $profile['registration_fee'], 'CASH'];
            $expenses[] = [2025, 'Welfare & Condolences', $profile['welfare_2025_date'], $profile['welfare_2025_text'], $profile['welfare_2025'], $profile['welfare_2025_payee'], 'MPESA', 'SOC'];
            $expenses[] = [2026, 'Welfare & Condolences', $profile['welfare_2026_date'], $profile['welfare_2026_text'], $profile['welfare_2026'], $profile['welfare_2026_payee'], 'MPESA', 'SOC'];
            $expenses[] = [2026, 'Training', '2026-08-23', 'Mafunzo ya usimamizi wa fedha kutoka Maendeleo ya Jamii.', $profile['training'], 'Ofisi ya Maendeleo ya Jamii', 'CASH'];

            $incomeSequence = 0;
            $expenseSequence = 0;

            foreach ($incomes as [$year, $category, $date, $description, $amount, $methodCode]) {
                DB::table('group_incomes')->insert($this->stamp([
                    'group_id' => $group->id,
                    'financial_cycle_id' => $cycles[$year]->id,
                    'group_fund_id' => $generalFundId,
                    'income_number' => sprintf('%s-INC-%d-%04d', $code, $year, ++$incomeSequence),
                    'income_category_id' => $this->lookupId('income_categories', $group->id, 'name', $category),
                    'income_date' => $date,
                    'description' => $description,
                    'amount' => $amount,
                    'payment_method_id' => $methodIds[$methodCode],
                    'external_reference' => $methodCode === 'CASH' ? null : $this->mobileReference(),
                    'status' => 'approved',
                    'received_by' => $treasurer->id,
                    'approved_by' => $chairId,
                    'approved_at' => Carbon::parse($date)->addDay()->setTime(10, 0),
                ]));
            }

            foreach ($expenses as $expense) {
                [$year, $category, $date, $description, $amount, $payee, $methodCode] = $expense;
                $fundCode = $expense[7] ?? 'GEN';
                $paidAt = Carbon::parse($date)->setTime(12, 0);

                DB::table('group_expenses')->insert($this->stamp([
                    'group_id' => $group->id,
                    'financial_cycle_id' => $cycles[$year]->id,
                    'group_fund_id' => $fundCode === 'SOC' ? $socialFundId : $generalFundId,
                    'expense_number' => sprintf('%s-EXP-%d-%04d', $code, $year, ++$expenseSequence),
                    'group_expense_category_id' => $this->lookupId('group_expense_categories', $group->id, 'name', $category),
                    'expense_date' => $date,
                    'description' => $description,
                    'amount' => $amount,
                    'payee_name' => $payee,
                    'payment_method_id' => $methodIds[$methodCode],
                    'external_reference' => $methodCode === 'CASH' ? null : $this->mobileReference(),
                    'status' => 'paid',
                    'requested_by' => $treasurer->id,
                    'approved_by' => $chairId,
                    'approved_at' => $paidAt->copy()->subDay(),
                    'paid_at' => $paidAt,
                ]));
            }
        }
    }

    /**
     * Amounts and payees per group.
     *
     * @return array<string, array<string, mixed>>
     */
    private function profiles(): array
    {
        return [
            'KND' => [
                'bank' => 'CRDB Bank PLC', 'bank_fee' => 2500, 'bank_interest' => 4200,
                'venue' => 'Ukumbi wa Kanisa la KKKT Kinondoni', 'venue_fee' => 5000, 'meeting_day' => 5,
                'stationery' => 15000, 'stationers' => 'Duka la Vifaa vya Ofisi - Kariakoo', 'transport' => 5000,
                'grant_2025' => 300000, 'grant_2025_date' => '2025-08-15',
                'grant_2025_text' => 'Msaada wa wanawake kutoka Manispaa ya Kinondoni.',
                'registration_fee' => 10000, 'new_member_date' => '2026-03-07',
                'welfare_2025' => 150000, 'welfare_2025_date' => '2025-09-20',
                'welfare_2025_text' => 'Rambirambi - msiba wa mama mzazi wa mwanachama.',
                'welfare_2025_payee' => 'Rehema Kileo',
                'welfare_2026' => 100000, 'welfare_2026_date' => '2026-05-18',
                'welfare_2026_text' => 'Rambirambi - msiba wa mume wa mwanachama.',
                'welfare_2026_payee' => 'Grace Mwakalinga',
                'training' => 40000,
            ],
            'ARU' => [
                'bank' => 'NMB Bank PLC', 'bank_fee' => 5000, 'bank_interest' => 9500,
                'venue' => 'Ukumbi wa Parokia ya Kijenge', 'venue_fee' => 12000, 'meeting_day' => 10,
                'stationery' => 30000, 'stationers' => 'Arusha Stationers Ltd', 'transport' => 10000,
                'grant_2025' => 500000, 'grant_2025_date' => '2025-07-22',
                'grant_2025_text' => 'Ruzuku ya vikundi vya wakulima kutoka Halmashauri ya Jiji la Arusha.',
                'registration_fee' => 20000, 'new_member_date' => '2026-03-14',
                'welfare_2025' => 300000, 'welfare_2025_date' => '2025-11-08',
                'welfare_2025_text' => 'Rambirambi - msiba wa baba mzazi wa mwanachama.',
                'welfare_2025_payee' => 'Peter Swai',
                'welfare_2026' => 200000, 'welfare_2026_date' => '2026-05-12',
                'welfare_2026_text' => 'Msaada wa dharura - ajali ya moto dukani.',
                'welfare_2026_payee' => 'Rose Kessy',
                'training' => 80000,
            ],
        ];
    }
}
