<?php

namespace App\Actions\UserManagement;

use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Models\UserManagement\Group;
use Illuminate\Support\Facades\DB;

class ProvisionGroupDefaults
{
    public function __construct(private ResolveFinancialCycle $resolveFinancialCycle) {}

    /**
     * Give a group everything it needs to start working (see config/group_defaults.php).
     * A table that already has rows for the group is left alone, so this is safe to repeat.
     */
    public function handle(Group $group, ?int $userId = null): void
    {
        $userId ??= $group->created_by;

        $this->provisionLookups($group->id);
        $this->provisionSettings($group);
        $this->provisionFunds($group, $userId);
        $this->provisionFinancialCycle($group, $userId);
        $this->provisionFineTypes($group);
        $this->provisionLoanTypes($group);
        $this->provisionLoanProducts($group, $userId);
    }

    /**
     * Insert the default rows of every per-group lookup table that is still empty for the group.
     */
    public function provisionLookups(int $groupId): void
    {
        $now = now();

        foreach (config('group_defaults.lookups') as $table => $rows) {
            if (DB::table($table)->where('group_id', $groupId)->exists()) {
                continue;
            }

            foreach ($rows as $index => $row) {
                DB::table($table)->insert([
                    'group_id' => $groupId,
                    'description' => null,
                    ...$row,
                    'display_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function provisionSettings(Group $group): void
    {
        if ($group->groupSetting()->exists()) {
            return;
        }

        $group->groupSetting()->create([
            'contribution_frequency_id' => $this->monthlyFrequencyId($group),
            'loan_interest_method_id' => $this->flatMethodId($group),
            'penalty_type_id' => $group->penaltyTypes()->where('name', 'Late Contribution')->value('id')
                ?? $group->penaltyTypes()->orderBy('display_order')->value('id'),
            ...config('group_defaults.settings'),
        ]);
    }

    private function provisionFunds(Group $group, int $userId): void
    {
        if ($group->groupFunds()->exists()) {
            return;
        }

        foreach (config('group_defaults.funds') as [$code, $name, $description, $minimum, $mandatory, $distributable, $loanable]) {
            $group->groupFunds()->create([
                'fund_type_id' => $group->fundTypes()->where('code', $code)->value('id'),
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'minimum_contribution' => $minimum,
                'contribution_frequency_id' => $mandatory ? $this->monthlyFrequencyId($group) : null,
                'is_mandatory' => $mandatory,
                'is_distributable' => $distributable,
                'is_loanable' => $loanable,
                'status' => 'active',
                'created_by' => $userId,
            ]);
        }
    }

    private function provisionFinancialCycle(Group $group, int $userId): void
    {
        if ($group->financialCycles()->exists()) {
            return;
        }

        $this->resolveFinancialCycle->handle($group, now(), $userId);
    }

    private function provisionFineTypes(Group $group): void
    {
        if ($group->fineTypes()->exists()) {
            return;
        }

        foreach (config('group_defaults.fine_types') as [$code, $name, $description, $amount, $type]) {
            $group->fineTypes()->create([
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'default_amount' => $amount,
                'calculation_type' => $type,
                'status' => 'active',
            ]);
        }
    }

    private function provisionLoanTypes(Group $group): void
    {
        if ($group->loanTypes()->exists()) {
            return;
        }

        foreach (config('group_defaults.loan_types') as $index => $name) {
            $group->loanTypes()->create(['name' => $name, 'display_order' => $index + 1, 'is_active' => true]);
        }
    }

    private function provisionLoanProducts(Group $group, int $userId): void
    {
        $savingsFundId = $group->groupFunds()->where('code', 'SAV')->value('id');

        if ($group->loanProducts()->exists() || $savingsFundId === null) {
            return;
        }

        foreach (config('group_defaults.loan_products') as [$code, $name, $minMonths, $maxMonths, $minAmount, $maxAmount]) {
            $group->loanProducts()->create([
                'group_fund_id' => $savingsFundId,
                'repayment_frequency_id' => $group->repaymentFrequencies()->where('interval_days', 30)->value('id'),
                'loan_interest_method_id' => $this->flatMethodId($group),
                'name' => $name,
                'code' => $code,
                'minimum_amount' => $minAmount,
                'maximum_amount' => $maxAmount,
                'maximum_savings_multiplier' => config('group_defaults.settings.maximum_loan_multiplier'),
                'interest_rate' => config('group_defaults.loan_product_interest_rate'),
                'interest_period' => $group->groupSetting->default_interest_period ?? config('group_defaults.settings.default_interest_period'),
                'minimum_duration_months' => $minMonths,
                'maximum_duration_months' => $maxMonths,
                'grace_period_days' => config('group_defaults.settings.loan_grace_period_days'),
                'guarantors_required' => 2,
                'status' => 'active',
                'created_by' => $userId,
            ]);
        }
    }

    private function monthlyFrequencyId(Group $group): ?int
    {
        return $group->contributionFrequencies()->where('name', 'like', 'Monthly%')->value('id')
            ?? $group->contributionFrequencies()->orderBy('display_order')->value('id');
    }

    private function flatMethodId(Group $group): ?int
    {
        return $group->loanInterestMethods()->where('code', 'FLAT')->value('id')
            ?? $group->loanInterestMethods()->orderBy('display_order')->value('id');
    }
}
