<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoanSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed loan applications, guarantors, loans, installments and repayments.
     */
    public function run(): void
    {
        mt_srand(2027);

        $today = Carbon::parse(self::TODAY);

        foreach (DB::table('groups')->orderBy('id')->get() as $group) {
            $code = $this->groupCode($group);
            $members = DB::table('group_members')->where('group_id', $group->id)->orderBy('id')->get();
            $methodIds = $this->paymentMethodIds($group->id);
            $treasurerId = $this->leaderUserId($group->id, 'Treasurer');
            $secretaryId = $this->leaderUserId($group->id, 'Secretary');
            $chairId = $this->leaderUserId($group->id, 'Chairperson');
            $cycles = DB::table('financial_cycles')->where('group_id', $group->id)->get()
                ->keyBy(fn ($cycle) => (int) substr($cycle->start_date, 0, 4));

            $applicationSequence = 0;
            $loanSequence = 0;
            $repaymentSequence = 0;

            foreach ($this->plan()[$code] as [$year, $memberIndex, $productCode, $amount, $months, $disbursedOn, $purpose, $stopsPayingAfter]) {
                $member = $members[$memberIndex];
                $product = DB::table('loan_products')->where('group_id', $group->id)->where('code', $productCode)->first();
                $disbursed = Carbon::parse($disbursedOn);
                $applicationDate = $disbursed->copy()->subDays(10);

                $applicationId = DB::table('loan_applications')->insertGetId($this->stamp([
                    'group_id' => $group->id,
                    'financial_cycle_id' => $cycles[$year]->id,
                    'loan_product_id' => $product->id,
                    'group_member_id' => $member->id,
                    'application_number' => sprintf('%s-LA-%d-%04d', $code, $year, ++$applicationSequence),
                    'requested_amount' => $amount,
                    'approved_amount' => $amount,
                    'purpose' => $purpose,
                    'duration_months' => $months,
                    'interest_rate' => $product->interest_rate,
                    'loan_interest_method_id' => $product->loan_interest_method_id,
                    'application_date' => $applicationDate->toDateString(),
                    'status' => 'approved',
                    'reviewed_by' => $secretaryId,
                    'reviewed_at' => $applicationDate->copy()->addDays(3)->setTime(11, 0),
                    'approved_by' => $chairId,
                    'approved_at' => $applicationDate->copy()->addDays(6)->setTime(15, 0),
                    'rejection_reason' => null,
                ]));

                $this->seedGuarantors($applicationId, $members, $memberIndex, $amount, $applicationDate);

                // Flat interest: rate % of the principal for every month of the loan.
                $interest = (int) round($amount * $product->interest_rate / 100 * $months);
                $principalBase = (int) (floor($amount / $months / 100) * 100);
                $interestBase = (int) (floor($interest / $months / 100) * 100);

                $installments = [];
                $penaltyTotal = 0;
                $paidTotal = 0;

                for ($number = 1; $number <= $months; $number++) {
                    $dueDate = $disbursed->copy()->addMonths($number);
                    $principalDue = $number === $months ? $amount - $principalBase * ($months - 1) : $principalBase;
                    $interestDue = $number === $months ? $interest - $interestBase * ($months - 1) : $interestBase;
                    $base = $principalDue + $interestDue;

                    $isDue = $dueDate->lte($today);
                    $isPaid = $isDue && ($stopsPayingAfter === null || $number <= $stopsPayingAfter);
                    $isOverdue = $isDue && ! $isPaid;
                    $penalty = $isOverdue ? (int) (round($base * 0.05 / 100) * 100) : 0;

                    $paidAt = $isPaid ? $dueDate->copy()->addDays(mt_rand(0, 2))->min($today)->setTime(mt_rand(9, 17), 0) : null;

                    $installments[] = [
                        'number' => $number,
                        'due_date' => $dueDate,
                        'principal_due' => $principalDue,
                        'interest_due' => $interestDue,
                        'penalty_due' => $penalty,
                        'total_due' => $base + $penalty,
                        'paid' => $isPaid,
                        'paid_at' => $paidAt,
                        'status' => $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'pending'),
                    ];

                    $penaltyTotal += $penalty;
                    $paidTotal += $isPaid ? $base : 0;
                }

                $totalPayable = $amount + $interest + $penaltyTotal;
                $outstanding = $totalPayable - $paidTotal;
                $status = $outstanding === 0 ? 'completed' : ($penaltyTotal > 0 ? 'overdue' : 'active');

                $loanId = DB::table('loans')->insertGetId($this->stamp([
                    'group_id' => $group->id,
                    'financial_cycle_id' => $cycles[$year]->id,
                    'loan_application_id' => $applicationId,
                    'group_member_id' => $member->id,
                    'loan_product_id' => $product->id,
                    'loan_number' => sprintf('%s-LN-%d-%04d', $code, $year, ++$loanSequence),
                    'principal_amount' => $amount,
                    'interest_amount' => $interest,
                    'penalty_amount' => $penaltyTotal,
                    'total_payable' => $totalPayable,
                    'amount_paid' => $paidTotal,
                    'outstanding_balance' => $outstanding,
                    'disbursement_date' => $disbursed->toDateString(),
                    'first_payment_date' => $installments[0]['due_date']->toDateString(),
                    'maturity_date' => end($installments)['due_date']->toDateString(),
                    'interest_rate' => $product->interest_rate,
                    'loan_interest_method_id' => $product->loan_interest_method_id,
                    'status' => $status,
                ]));

                foreach ($installments as $installment) {
                    $base = $installment['principal_due'] + $installment['interest_due'];

                    DB::table('loan_installments')->insert($this->stamp([
                        'loan_id' => $loanId,
                        'installment_number' => $installment['number'],
                        'due_date' => $installment['due_date']->toDateString(),
                        'principal_due' => $installment['principal_due'],
                        'interest_due' => $installment['interest_due'],
                        'penalty_due' => $installment['penalty_due'],
                        'total_due' => $installment['total_due'],
                        'principal_paid' => $installment['paid'] ? $installment['principal_due'] : 0,
                        'interest_paid' => $installment['paid'] ? $installment['interest_due'] : 0,
                        'penalty_paid' => 0,
                        'total_paid' => $installment['paid'] ? $base : 0,
                        'status' => $installment['status'],
                        'paid_at' => $installment['paid_at'],
                    ]));

                    if (! $installment['paid']) {
                        continue;
                    }

                    [$methodId, $reference] = $this->randomPayment($methodIds);

                    DB::table('loan_repayments')->insert($this->stamp([
                        'loan_id' => $loanId,
                        'group_member_id' => $member->id,
                        'repayment_number' => sprintf('%s-LR-%d-%06d', $code, $year, ++$repaymentSequence),
                        'payment_date' => $installment['paid_at']->toDateString(),
                        'amount' => $base,
                        'principal_amount' => $installment['principal_due'],
                        'interest_amount' => $installment['interest_due'],
                        'penalty_amount' => 0,
                        'payment_method_id' => $methodId,
                        'external_reference' => $reference,
                        'status' => 'approved',
                        'received_by' => $treasurerId,
                        'approved_by' => $chairId,
                        'approved_at' => $installment['paid_at']->copy()->addDay(),
                    ]));
                }
            }

            $this->seedOtherApplications($group, $code, $members, $cycles[2026]->id, $applicationSequence, $secretaryId, $chairId);
        }
    }

    /**
     * Two guarantor members for an application.
     *
     * @param  Collection<int, object>  $members
     */
    private function seedGuarantors(int $applicationId, $members, int $borrowerIndex, int $amount, Carbon $applicationDate): void
    {
        foreach ([1, 2] as $offset) {
            $guarantor = $members[($borrowerIndex + $offset) % $members->count()];

            DB::table('loan_guarantors')->insert($this->stamp([
                'loan_application_id' => $applicationId,
                'group_member_id' => $guarantor->id,
                'guaranteed_amount' => (int) (round($amount / 2 / 100) * 100),
                'status' => 'accepted',
                'responded_at' => $applicationDate->copy()->addDay()->setTime(14, 0),
                'remarks' => 'Nakubali kumdhamini.',
            ]));
        }
    }

    /**
     * One rejected and one pending application that never became loans.
     *
     * @param  Collection<int, object>  $members
     */
    private function seedOtherApplications(object $group, string $code, $members, int $cycleId, int $sequence, int $secretaryId, int $chairId): void
    {
        $product = DB::table('loan_products')->where('group_id', $group->id)->where('code', 'MAENDELEO')->first();
        $scale = $code === 'KND' ? 1 : 3;

        DB::table('loan_applications')->insert([
            $this->stamp([
                'group_id' => $group->id,
                'financial_cycle_id' => $cycleId,
                'loan_product_id' => $product->id,
                'group_member_id' => $members[8]->id,
                'application_number' => sprintf('%s-LA-2026-%04d', $code, $sequence + 1),
                'requested_amount' => 2500000 * $scale,
                'approved_amount' => null,
                'purpose' => 'Kununua kiwanja Kigamboni',
                'duration_months' => 12,
                'interest_rate' => $product->interest_rate,
                'loan_interest_method_id' => $product->loan_interest_method_id,
                'application_date' => '2026-06-20',
                'status' => 'rejected',
                'reviewed_by' => $secretaryId,
                'reviewed_at' => '2026-06-23 11:00:00',
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => 'Kiasi kinazidi mara tatu ya akiba ya mwanachama.',
            ]),
            $this->stamp([
                'group_id' => $group->id,
                'financial_cycle_id' => $cycleId,
                'loan_product_id' => $product->id,
                'group_member_id' => $members[7]->id,
                'application_number' => sprintf('%s-LA-2026-%04d', $code, $sequence + 2),
                'requested_amount' => 700000 * $scale,
                'approved_amount' => null,
                'purpose' => 'Kununua mashine ya kusaga nafaka',
                'duration_months' => 8,
                'interest_rate' => $product->interest_rate,
                'loan_interest_method_id' => $product->loan_interest_method_id,
                'application_date' => '2026-10-28',
                'status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
            ]),
        ]);
    }

    /**
     * Loans to create per group: [cycle year, member index, product, amount, months, disbursed on, purpose, installments paid before the member stops paying (null = pays on time)].
     *
     * @return array<string, array<int, array{0: int, 1: int, 2: string, 3: int, 4: int, 5: string, 6: string, 7: int|null}>>
     */
    private function plan(): array
    {
        return [
            'KND' => [
                [2025, 1, 'DHARURA', 150000, 3, '2025-03-08', 'Matibabu ya mtoto', null],
                [2025, 4, 'MAENDELEO', 600000, 6, '2025-04-05', 'Mtaji wa biashara ya mama lishe', null],
                [2025, 6, 'ELIMU', 400000, 4, '2025-06-07', 'Ada ya shule - muhula wa pili', null],
                [2026, 2, 'MAENDELEO', 800000, 10, '2026-02-07', 'Kununua cherehani na vifaa vya ushonaji', null],
                [2026, 5, 'DHARURA', 200000, 3, '2026-05-09', 'Gharama za msiba', null],
                [2026, 7, 'MAENDELEO', 500000, 8, '2026-04-04', 'Kuongeza mtaji wa duka la mchele', 3],
                [2026, 3, 'ELIMU', 300000, 6, '2026-07-04', 'Ada ya chuo cha ufundi VETA', null],
            ],
            'ARU' => [
                [2025, 1, 'DHARURA', 500000, 3, '2025-02-15', 'Ada ya shule ya sekondari', null],
                [2025, 3, 'MAENDELEO', 2000000, 9, '2025-03-14', 'Kununua mbegu na mbolea za maharage', null],
                [2025, 5, 'ELIMU', 1200000, 6, '2025-05-16', 'Ada ya chuo kikuu - Mzumbe', null],
                [2026, 2, 'MAENDELEO', 3000000, 12, '2026-03-13', 'Kununua power tiller kwa kilimo cha mbogamboga', null],
                [2026, 4, 'DHARURA', 600000, 3, '2026-06-12', 'Matibabu hospitali ya Mount Meru', null],
                [2026, 6, 'ELIMU', 1500000, 8, '2026-02-13', 'Ada ya shule ya kimataifa', null],
                [2026, 8, 'MAENDELEO', 1800000, 6, '2026-05-15', 'Kuongeza mtaji wa duka la pembejeo', 2],
            ],
        ];
    }
}
