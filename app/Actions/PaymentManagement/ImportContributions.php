<?php

namespace App\Actions\PaymentManagement;

use App\Actions\Concerns\ReadsCsvUpload;
use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\GroupFund;
use App\Models\PaymentManagement\PaymentMethod;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\User;
use App\Support\ImportDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportContributions
{
    use ReadsCsvUpload;

    public const MAX_ROWS = 500;

    /**
     * @var array<int, string>
     */
    public const REQUIRED = ['phone_number', 'amount', 'transaction_date'];

    /**
     * @var array<int, string>
     */
    public const OPTIONAL = ['receipt'];

    public function __construct(private ResolveFinancialCycle $resolveCycle, private RecordPaymentTransaction $recordPayment) {}

    /**
     * Record contributions to one fund from a CSV or Excel file. Each row names a member by phone number, the amount paid and the
     * date; the month paid for is the month of the date. Every row becomes an approved contribution with its payment transaction.
     * Rows that cannot be recorded, or were recorded before (same receipt), are skipped and reported.
     *
     * @return array{imported: int, skipped: array<int, array{row: int, name: string, phone: string, reason: string}>}
     *
     * @throws InvalidArgumentException when the file is not a usable import file
     */
    public function handle(Group $group, User $user, UploadedFile $file, GroupFund $fund, PaymentMethod $method): array
    {
        $rows = $this->readCsv($file, self::REQUIRED, self::OPTIONAL, self::MAX_ROWS);

        return DB::transaction(function () use ($group, $user, $rows, $fund, $method): array {
            $imported = 0;
            $skipped = [];

            foreach ($rows as $number => $row) {
                [$reason, $name] = $this->record($group, $user, $row, $fund, $method);

                if ($reason === null) {
                    $imported++;
                } else {
                    $skipped[] = ['row' => $number, 'name' => $name, 'phone' => $row['phone_number'], 'reason' => $reason];
                }
            }

            return ['imported' => $imported, 'skipped' => $skipped];
        });
    }

    /**
     * Record one row, returning [why it was skipped or null, the member's name].
     *
     * @param  array<string, string>  $row
     * @return array{0: string|null, 1: string}
     */
    private function record(Group $group, User $user, array $row, GroupFund $fund, PaymentMethod $method): array
    {
        $phone = $this->cleanPhone($row['phone_number']);
        $member = $phone === '' ? null : $group->groupMembers()->where('phone_number', $phone)->first();

        if ($member === null) {
            return ['No member of this group has that phone number.', ''];
        }

        if ($member->membership_status !== 'active') {
            return ['The member is not active.', $member->name];
        }

        $cents = $this->amountInCents($row['amount']);

        if ($cents === null) {
            return ['The amount must be a positive number.', $member->name];
        }

        $date = ImportDate::parse($row['transaction_date']);

        if ($date === null || $date->toDateString() > today()->toDateString()) {
            return ['The transaction date is required and must be a past or current date, e.g. 15/12/2025 08:32:47 or 2025-12-15.', $member->name];
        }

        $receipt = $row['receipt'] === '' ? null : mb_substr($row['receipt'], 0, 255);

        if ($receipt !== null && $this->receiptUsed($group, $receipt)) {
            return ["Already imported: receipt {$receipt} is already recorded in this group.", $member->name];
        }

        if ($receipt === null && $this->alreadyImported($group, $member, $fund, $date, $cents)) {
            return ['Already imported: this member already has a contribution to this fund with the same date and amount (and no receipt number to tell them apart).', $member->name];
        }

        $this->create($group, $user, $member, $fund, $method, $date, $cents / 100, $receipt);

        return [null, $member->name];
    }

    private function create(Group $group, User $user, GroupMember $member, GroupFund $fund, PaymentMethod $method, Carbon $date, float $amount, ?string $receipt): void
    {
        $period = $date->copy()->startOfMonth();
        $stamps = ['created_at' => $date, 'updated_at' => $date];

        $contribution = $group->contributions()->forceCreate([
            'group_id' => $group->id,
            'financial_cycle_id' => $this->resolveCycle->handle($group, $period, $user->id)->id,
            'group_member_id' => $member->id,
            'contribution_number' => 'PENDING-'.uniqid(),
            'contribution_date' => $date->toDateString(),
            'total_amount' => $amount,
            'payment_method_id' => $method->id,
            'external_reference' => $receipt,
            'status' => 'approved',
            'received_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => $date,
            ...$stamps,
        ]);

        $contribution->contributionItems()->forceCreate([
            'contribution_id' => $contribution->id,
            'group_fund_id' => $fund->id,
            'period_start' => $period->toDateString(),
            'period_end' => $period->copy()->endOfMonth()->toDateString(),
            'amount' => $amount,
            ...$stamps,
        ]);

        $contribution->withoutTimestamps(fn () => $contribution->update(['contribution_number' => sprintf('CON-%d-%06d', $date->year, $contribution->id)]));
        $this->recordPayment->handle($contribution, $user);
    }

    /**
     * Whether a contribution with this receipt number is already recorded in the group, however it was entered.
     */
    private function receiptUsed(Group $group, string $receipt): bool
    {
        return $group->contributions()
            ->whereRaw('lower(trim(external_reference)) = ?', [mb_strtolower(trim($receipt))])
            ->exists();
    }

    /**
     * Without a receipt: whether the member already has a receipt-less contribution to the fund on the date for the amount.
     */
    private function alreadyImported(Group $group, GroupMember $member, GroupFund $fund, Carbon $date, int $cents): bool
    {
        return $group->contributions()
            ->where('group_member_id', $member->id)
            ->whereDate('contribution_date', $date->toDateString())
            ->whereNull('external_reference')
            ->where('total_amount', number_format($cents / 100, 2, '.', ''))
            ->whereHas('contributionItems', fn ($items) => $items->where('group_fund_id', $fund->id))
            ->exists();
    }
}
