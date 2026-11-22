<?php

namespace App\Actions\PaymentManagement;

use App\Models\PaymentManagement\Contribution;
use App\Models\UserManagement\Group;

class BuildContributionExport
{
    /**
     * The columns a contributions export can contain: label, whether it is an amount (and so totalled), and how to read it from a
     * contribution. Besides the fixed columns, every fund of the group has an amount column of its own (fund_<id>).
     * Contributions must be loaded with groupMember, paymentMethod, contributionItems.groupFund, financialCycle, receiver and approver.
     *
     * @return array<string, array{label: string, amount: bool, value: callable(Contribution): (string|float|null)}>
     */
    public static function columns(Group $group): array
    {
        $columns = [
            'contribution_number' => ['label' => 'Number', 'amount' => false, 'value' => fn (Contribution $row) => $row->contribution_number],
            'member' => ['label' => 'Member', 'amount' => false, 'value' => fn (Contribution $row) => $row->groupMember->name],
            'member_number' => ['label' => 'Member number', 'amount' => false, 'value' => fn (Contribution $row) => $row->groupMember->member_number],
            'funds' => ['label' => 'Funds', 'amount' => false, 'value' => fn (Contribution $row) => $row->contributionItems->map(fn ($item) => $item->groupFund->name.': '.number_format((float) $item->amount, 2))->implode('; ')],
        ];

        foreach ($group->groupFunds()->orderBy('name')->get(['id', 'name']) as $fund) {
            $columns["fund_{$fund->id}"] = ['label' => $fund->name, 'amount' => true, 'value' => fn (Contribution $row) => (float) $row->contributionItems->where('group_fund_id', $fund->id)->sum('amount')];
        }

        return [
            ...$columns,
            'total_amount' => ['label' => 'Total', 'amount' => true, 'value' => fn (Contribution $row) => (float) $row->total_amount],
            'payment_method' => ['label' => 'Payment method', 'amount' => false, 'value' => fn (Contribution $row) => $row->paymentMethod->name],
            'period' => ['label' => 'Month', 'amount' => false, 'value' => fn (Contribution $row) => ($row->contributionItems->first()?->period_start ?? $row->contribution_date)->format('Y-m')],
            'contribution_date' => ['label' => 'Date paid', 'amount' => false, 'value' => fn (Contribution $row) => $row->contribution_date->toDateString()],
            'status' => ['label' => 'Status', 'amount' => false, 'value' => fn (Contribution $row) => ucfirst($row->status)],
            'external_reference' => ['label' => 'Reference', 'amount' => false, 'value' => fn (Contribution $row) => $row->external_reference],
            'cycle' => ['label' => 'Financial cycle', 'amount' => false, 'value' => fn (Contribution $row) => $row->financialCycle?->name],
            'received_by' => ['label' => 'Received by', 'amount' => false, 'value' => fn (Contribution $row) => $row->receiver?->name],
            'approved_by' => ['label' => 'Approved by', 'amount' => false, 'value' => fn (Contribution $row) => $row->approver?->name],
            'notes' => ['label' => 'Notes', 'amount' => false, 'value' => fn (Contribution $row) => $row->notes],
        ];
    }
}
