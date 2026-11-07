<?php

namespace App\Actions\ShareManagement;

use App\Actions\Concerns\ReadsCsvUpload;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use App\Support\BillingPeriod;
use App\Support\ImportDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

    public function __construct(private RecordSharePurchase $recordPurchase) {}

    /**
     * Record share purchases from a CSV file. Each row names a member by phone number, the amount they paid and the date; the number of
     * shares is the amount divided by the group's share price, and the period is the month of the date. Rows that cannot be recorded
     * are skipped and reported.
     *
     * @return array{imported: int, carried: int, skipped: array<int, array{row: int, name: string, phone: string, reason: string}>}
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
            $carried = 0;
            $skipped = [];

            foreach ($rows as $number => $row) {
                [$reason, $name, $months] = $this->record($group, $user, $row, $setting->maximum_shares, $price);

                if ($reason === null) {
                    $imported++;
                    $carried += $months > 1 ? 1 : 0;
                } else {
                    $skipped[] = ['row' => $number, 'name' => $name, 'phone' => $row['phone_number'], 'reason' => $reason];
                }
            }

            return ['imported' => $imported, 'carried' => $carried, 'skipped' => $skipped];
        });
    }

    /**
     * Record one row, returning [why it was skipped or null, the member's name, how many months it was spread over].
     *
     * @param  array<string, string>  $row
     * @return array{0: string|null, 1: string, 2: int}
     */
    private function record(Group $group, User $user, array $row, ?int $maximumShares, float $price): array
    {
        $phone = $this->cleanPhone($row['phone_number']);
        $member = $phone === '' ? null : $group->groupMembers()->where('phone_number', $phone)->first();

        if ($member === null) {
            return ['No member of this group has that phone number.', '', 0];
        }

        if ($member->membership_status !== 'active') {
            return ['The member is not active.', $member->name, 0];
        }

        $amount = str_replace(',', '', $row['amount']);

        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $amount) !== 1 || (float) $amount <= 0) {
            return ['The amount must be a positive number.', $member->name, 0];
        }

        $priceInCents = (int) round($price * 100);
        $amountInCents = (int) round((float) $amount * 100);

        if ($amountInCents % $priceInCents !== 0) {
            return ['The amount must be a multiple of the share price ('.number_format($price, 2).').', $member->name, 0];
        }

        $shares = intdiv($amountInCents, $priceInCents);
        $date = ImportDate::parse($row['transaction_date']);

        if ($date === null || $date->toDateString() > today()->toDateString()) {
            return ['The transaction date is required and must be a past or current date, e.g. 15/12/2025 08:32:47 or 2025-12-15.', $member->name, 0];
        }

        $period = BillingPeriod::start(null, $date);
        $reference = $row['reference'] === '' ? null : mb_substr($row['reference'], 0, 255);

        if ($this->alreadyImported($member->id, $date, $reference, $amountInCents)) {
            return ['Already imported: this member already has shares with the same date, amount and reference.', $member->name, 0];
        }

        try {
            $records = $this->recordPurchase->handle(
                $group, $member, $user, $shares, $date, $period,
                $reference,
                $price, $maximumShares, $date,
            );
        } catch (InvalidArgumentException $exception) {
            return [$exception->getMessage(), $member->name, 0];
        }

        return [null, $member->name, count($records)];
    }

    /**
     * Whether the member already has share purchases on the date, with the same reference, adding up to the row's amount. A purchase
     * that was carried over several months adds up across its records, so uploading the same file twice records nothing new.
     */
    private function alreadyImported(int $memberId, Carbon $date, ?string $reference, int $amountInCents): bool
    {
        $total = MemberShareTransaction::query()
            ->where('group_member_id', $memberId)
            ->where('transaction_type', 'purchase')
            ->whereDate('transaction_date', $date->toDateString())
            ->when($reference === null, fn ($query) => $query->whereNull('reference'), fn ($query) => $query->where('reference', $reference))
            ->sum('total_amount');

        return $total > 0 && (int) round((float) $total * 100) === $amountInCents;
    }
}
