<?php

namespace App\Actions\ShareManagement;

use App\Actions\Concerns\ReadsCsvUpload;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use App\Support\BillingPeriod;
use App\Support\ImportDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportMemberShares
{
    use ReadsCsvUpload;

    public const MAX_ROWS = 500;

    /**
     * Required and optional columns of the import file. The phone number identifies the member.
     *
     * @var array<int, string>
     */
    public const REQUIRED = ['phone_number', 'amount', 'transaction_date'];

    /**
     * @var array<int, string>
     */
    public const OPTIONAL = ['reference'];

    public function __construct(private ResolveFinancialCycle $resolveCycle) {}

    /**
     * Record share purchases from a CSV file. Each row names a member by phone number, the amount they paid and the date; the number of
     * shares is the amount divided by the group's share price, and the period is the month of the date. Rows that cannot be recorded
     * are skipped and reported.
     *
     * @return array{imported: int, skipped: array<int, array{row: int, name: string, phone: string, reason: string}>}
     *
     * @throws InvalidArgumentException when the file is not a usable import file or the group has no share price
     */
    public function handle(Group $group, User $user, UploadedFile $file): array
    {
        $rows = $this->readCsv($file, self::REQUIRED, self::OPTIONAL, self::MAX_ROWS);
        $setting = $group->ensureGroupSetting();
        $price = (float) $setting->share_price;

        if ($price <= 0) {
            throw new InvalidArgumentException('Set the share price in the group settings first.');
        }

        return DB::transaction(function () use ($group, $user, $rows, $setting, $price): array {
            $imported = 0;
            $skipped = [];

            foreach ($rows as $number => $row) {
                [$reason, $name] = $this->record($group, $user, $row, $setting->maximum_shares, $price);

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
    private function record(Group $group, User $user, array $row, ?int $maximumShares, float $price): array
    {
        $phone = $this->cleanPhone($row['phone_number']);
        $member = $phone === '' ? null : $group->groupMembers()->where('phone_number', $phone)->first();

        if ($member === null) {
            return ['No member of this group has that phone number.', ''];
        }

        if ($member->membership_status !== 'active') {
            return ['The member is not active.', $member->name];
        }

        $amount = str_replace(',', '', $row['amount']);

        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $amount) !== 1 || (float) $amount <= 0) {
            return ['The amount must be a positive number.', $member->name];
        }

        $priceInCents = (int) round($price * 100);
        $amountInCents = (int) round((float) $amount * 100);

        if ($amountInCents % $priceInCents !== 0) {
            return ['The amount must be a multiple of the share price ('.number_format($price, 2).').', $member->name];
        }

        $shares = intdiv($amountInCents, $priceInCents);
        $date = ImportDate::parse($row['transaction_date']);

        if ($date === null || $date->toDateString() > today()->toDateString()) {
            return ['The transaction date is required and must be a past or current date, e.g. 15/12/2025 08:32:47 or 2025-12-15.', $member->name];
        }

        $period = BillingPeriod::start(null, $date);

        $held = (int) MemberShareTransaction::query()
            ->where('group_member_id', $member->id)
            ->where('transaction_type', 'purchase')
            ->whereDate('period_start', $period->toDateString())
            ->sum('number_of_shares');

        if ($maximumShares !== null && $held + $shares > $maximumShares) {
            return ["A member can buy at most {$maximumShares} shares in {$period->format('F Y')} (currently {$held}).", $member->name];
        }

        $transaction = $group->memberShareTransactions()->forceCreate([
            'financial_cycle_id' => $this->resolveCycle->handle($group, $period, $user->id)->id,
            'group_member_id' => $member->id,
            'transaction_number' => 'PENDING-'.uniqid(),
            'transaction_type' => 'purchase',
            'number_of_shares' => $shares,
            'price_per_share' => $price,
            'total_amount' => round($shares * $price, 2),
            'transaction_date' => $date->toDateString(),
            'period_start' => $period->toDateString(),
            'status' => 'approved',
            'reference' => $row['reference'] === '' ? null : mb_substr($row['reference'], 0, 255),
            'approved_by' => $user->id,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $transaction->withoutTimestamps(fn () => $transaction->update(['transaction_number' => sprintf('SHR-%d-%06d', $date->year, $transaction->id)]));

        return [null, $member->name];
    }
}
