<?php

namespace Database\Seeders;

use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\FinePayment;
use App\Models\PaymentManagement\GroupExpense;
use App\Models\PaymentManagement\GroupIncome;
use App\Models\ShareManagement\ShareOutPayment;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentTransactionSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Provider names for non-cash payment method codes.
     *
     * @var array<string, string>
     */
    private const PROVIDERS = [
        'MPESA' => 'Vodacom M-Pesa',
        'TIGOPESA' => 'Tigo Pesa',
        'AIRTELMONEY' => 'Airtel Money',
        'HALOPESA' => 'HaloPesa',
        'BANK' => 'Bank Transfer',
    ];

    /**
     * Log a payment transaction for every non-cash payment already seeded, plus two failed attempts.
     */
    public function run(): void
    {
        $sequence = 0;

        foreach ($this->sources() as [$type, $class, $query]) {
            foreach ($query->orderBy('txn_date')->orderBy('source_id')->get() as $row) {
                $initiated = Carbon::parse($row->txn_date)->setTime(10, 0);

                DB::table('payment_transactions')->insert($this->stamp([
                    'group_id' => $row->group_id,
                    'group_member_id' => $row->member_id,
                    'transaction_number' => sprintf('TXN-%s-%07d', $initiated->format('Y'), ++$sequence),
                    'transaction_type' => $type,
                    'payment_method_id' => $row->payment_method_id,
                    'amount' => $row->amount,
                    'currency' => 'TZS',
                    'provider' => self::PROVIDERS[$row->method_code],
                    'provider_transaction_id' => $row->external_reference,
                    'phone_number' => $row->phone_number && $row->method_code !== 'BANK' ? '255'.substr($row->phone_number, 1) : null,
                    'reference_type' => $class,
                    'reference_id' => $row->source_id,
                    'status' => 'completed',
                    'failure_reason' => null,
                    'initiated_at' => $initiated,
                    'completed_at' => $initiated->copy()->addMinutes(2),
                    'created_by' => $row->actor_id,
                ]));
            }
        }

        $this->seedFailedAttempts($sequence);
    }

    /**
     * Mobile-money prompts that never completed.
     */
    private function seedFailedAttempts(int $sequence): void
    {
        $failures = [
            ['MPESA', 'Salio halitoshi kukamilisha muamala.', '2026-04-05'],
            ['TIGOPESA', 'Muamala umekatika kutokana na mtandao.', '2026-09-10'],
        ];

        foreach ($failures as $index => [$methodCode, $reason, $date]) {
            $group = DB::table('groups')->orderBy('id')->skip($index)->first();
            $member = DB::table('group_members')
                ->join('users', 'users.id', '=', 'group_members.user_id')
                ->where('group_members.group_id', $group->id)
                ->orderBy('group_members.id')
                ->skip(3)
                ->first(['group_members.id', 'users.phone_number']);
            $initiated = Carbon::parse($date)->setTime(9, 30);

            DB::table('payment_transactions')->insert($this->stamp([
                'group_id' => $group->id,
                'group_member_id' => $member->id,
                'transaction_number' => sprintf('TXN-%s-%07d', $initiated->format('Y'), ++$sequence),
                'transaction_type' => 'contribution',
                'payment_method_id' => $this->lookupId('payment_methods', $group->id, 'code', $methodCode),
                'amount' => 25000,
                'currency' => 'TZS',
                'provider' => self::PROVIDERS[$methodCode],
                'provider_transaction_id' => null,
                'phone_number' => '255'.substr($member->phone_number, 1),
                'reference_type' => null,
                'reference_id' => null,
                'status' => 'failed',
                'failure_reason' => $reason,
                'initiated_at' => $initiated,
                'completed_at' => null,
                'created_by' => null,
            ]));
        }
    }

    /**
     * Each source normalises to: source_id, group_id, member_id, amount, payment_method_id,
     * external_reference, txn_date, actor_id, method_code, phone_number.
     *
     * @return array<int, array{0: string, 1: class-string, 2: Builder}>
     */
    private function sources(): array
    {
        $withMethod = fn (Builder $query, string $methodColumn) => $query
            ->join('payment_methods', 'payment_methods.id', '=', $methodColumn)
            ->where('payment_methods.code', '!=', 'CASH');

        return [
            ['contribution', Contribution::class, $withMethod(
                DB::table('contributions')
                    ->join('group_members', 'group_members.id', '=', 'contributions.group_member_id')
                    ->join('users', 'users.id', '=', 'group_members.user_id')
                    ->select('contributions.id as source_id', 'contributions.group_id', 'contributions.group_member_id as member_id', 'contributions.total_amount as amount', 'contributions.payment_method_id', 'contributions.external_reference', 'contributions.contribution_date as txn_date', 'contributions.received_by as actor_id', 'payment_methods.code as method_code', 'users.phone_number'),
                'contributions.payment_method_id',
            )],
            ['loan_repayment', LoanRepayment::class, $withMethod(
                DB::table('loan_repayments')
                    ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
                    ->join('group_members', 'group_members.id', '=', 'loan_repayments.group_member_id')
                    ->join('users', 'users.id', '=', 'group_members.user_id')
                    ->select('loan_repayments.id as source_id', 'loans.group_id', 'loan_repayments.group_member_id as member_id', 'loan_repayments.amount', 'loan_repayments.payment_method_id', 'loan_repayments.external_reference', 'loan_repayments.payment_date as txn_date', 'loan_repayments.received_by as actor_id', 'payment_methods.code as method_code', 'users.phone_number'),
                'loan_repayments.payment_method_id',
            )],
            ['fine_payment', FinePayment::class, $withMethod(
                DB::table('fine_payments')
                    ->join('member_fines', 'member_fines.id', '=', 'fine_payments.member_fine_id')
                    ->join('group_members', 'group_members.id', '=', 'member_fines.group_member_id')
                    ->join('users', 'users.id', '=', 'group_members.user_id')
                    ->select('fine_payments.id as source_id', 'member_fines.group_id', 'member_fines.group_member_id as member_id', 'fine_payments.amount', 'fine_payments.payment_method_id', 'fine_payments.external_reference', 'fine_payments.payment_date as txn_date', 'fine_payments.received_by as actor_id', 'payment_methods.code as method_code', 'users.phone_number'),
                'fine_payments.payment_method_id',
            )],
            ['share_out_payment', ShareOutPayment::class, $withMethod(
                DB::table('share_out_payments')
                    ->join('member_share_outs', 'member_share_outs.id', '=', 'share_out_payments.member_share_out_id')
                    ->join('share_outs', 'share_outs.id', '=', 'member_share_outs.share_out_id')
                    ->join('group_members', 'group_members.id', '=', 'member_share_outs.group_member_id')
                    ->join('users', 'users.id', '=', 'group_members.user_id')
                    ->select('share_out_payments.id as source_id', 'share_outs.group_id', 'member_share_outs.group_member_id as member_id', 'share_out_payments.amount', 'share_out_payments.payment_method_id', 'share_out_payments.external_reference', 'share_out_payments.payment_date as txn_date', 'share_out_payments.approved_by as actor_id', 'payment_methods.code as method_code', 'users.phone_number'),
                'share_out_payments.payment_method_id',
            )],
            ['income', GroupIncome::class, $withMethod(
                DB::table('group_incomes')
                    ->selectRaw('group_incomes.id as source_id, group_incomes.group_id, NULL as member_id, group_incomes.amount, group_incomes.payment_method_id, group_incomes.external_reference, group_incomes.income_date as txn_date, group_incomes.received_by as actor_id, payment_methods.code as method_code, NULL as phone_number'),
                'group_incomes.payment_method_id',
            )],
            ['expense_payment', GroupExpense::class, $withMethod(
                DB::table('group_expenses')
                    ->selectRaw('group_expenses.id as source_id, group_expenses.group_id, NULL as member_id, group_expenses.amount, group_expenses.payment_method_id, group_expenses.external_reference, group_expenses.expense_date as txn_date, group_expenses.requested_by as actor_id, payment_methods.code as method_code, NULL as phone_number'),
                'group_expenses.payment_method_id',
            )],
        ];
    }
}
