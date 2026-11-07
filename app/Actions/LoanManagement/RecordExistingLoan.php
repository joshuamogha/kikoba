<?php

namespace App\Actions\LoanManagement;

use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanApplication;
use App\Models\LoanManagement\LoanProduct;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordExistingLoan
{
    public function __construct(private ResolveFinancialCycle $resolveCycle, private DisburseLoan $disburse) {}

    /**
     * Record a loan that already happened in one step: an approved application, its accepted guarantors (optional) and the
     * disbursed loan with its installment schedule. The eligibility checks of new applications are skipped.
     *
     * @param  array{group_member_id: int, requested_amount: float|int|string, approved_amount?: float|int|string|null, duration_months?: int|string|null, purpose: string, guarantors?: array<int, array{group_member_id: int, guaranteed_amount: float|int|string}>}  $data
     * @return array{0: LoanApplication, 1: Loan}
     */
    public function handle(Group $group, LoanProduct $product, User $user, array $data, Carbon $applicationDate, Carbon $disbursementDate): array
    {
        return DB::transaction(function () use ($group, $product, $user, $data, $applicationDate, $disbursementDate): array {
            $approved = $data['approved_amount'] ?? $data['requested_amount'];

            $application = $group->loanApplications()->create([
                'financial_cycle_id' => $this->resolveCycle->handle($group, $applicationDate, $user->id)->id,
                'loan_product_id' => $product->id,
                'group_member_id' => $data['group_member_id'],
                'application_number' => 'PENDING-'.uniqid(),
                'requested_amount' => $data['requested_amount'],
                'approved_amount' => $approved,
                'purpose' => $data['purpose'],
                'duration_months' => $product->termFor((float) $approved) ?? $data['duration_months'] ?? null,
                'interest_rate' => $product->interest_rate,
                'loan_interest_method_id' => $product->loan_interest_method_id,
                'application_date' => $applicationDate->toDateString(),
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => $applicationDate->copy()->setTime(12, 0),
                'approved_by' => $user->id,
                'approved_at' => $applicationDate->copy()->setTime(12, 0),
            ]);
            $application->update(['application_number' => sprintf('LA-%d-%06d', $applicationDate->year, $application->id)]);

            foreach ($data['guarantors'] ?? [] as $guarantor) {
                $application->loanGuarantors()->create([
                    'group_member_id' => $guarantor['group_member_id'],
                    'guaranteed_amount' => $guarantor['guaranteed_amount'],
                    'status' => 'accepted',
                    'responded_at' => $applicationDate->copy()->setTime(12, 0),
                ]);
            }

            return [$application, $this->disburse->handle($application, $user, $disbursementDate)];
        });
    }
}
