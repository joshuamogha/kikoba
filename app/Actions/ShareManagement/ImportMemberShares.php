<?php

namespace App\Actions\ShareManagement;

use App\Actions\Concerns\ReadsCsvUpload;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use App\Support\BillingPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class ImportMemberShares
{
    use ReadsCsvUpload;

    public const MAX_ROWS = 500;

    /**
     * Required and optional columns of the import file. The phone number identifies the member.
     *
     * @var array<int, string>
     */
    public const REQUIRED = ['phone_number', 'number_of_shares'];

    /**
     * @var array<int, string>
     */
    public const OPTIONAL = ['transaction_date', 'period', 'reference'];

    public function __construct(private ResolveFinancialCycle $resolveCycle) {}

    /**
     * Record share purchases from a CSV file. Each row names a member by phone number and how many shares they bought, at the group's
     * share price, on the given date (today when empty). Rows that cannot be recorded are skipped and reported.
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
        $phone = User::normalizePhone($row['phone_number']);
        $member = $phone === '' ? null : $group->groupMembers()->where('phone_number', $phone)->first();

        if ($member === null) {
            return ['No member of this group has that phone number.', ''];
        }

        if ($member->membership_status !== 'active') {
            return ['The member is not active.', $member->name];
        }

        if (preg_match('/^[1-9][0-9]{0,6}$/', $row['number_of_shares']) !== 1) {
            return ['The number of shares must be a whole number of 1 or more.', $member->name];
        }

        $shares = (int) $row['number_of_shares'];
        $date = $this->parseDate($row['transaction_date']);

        if ($date === null || $date->isFuture()) {
            return ['The date must be a past or current date written as YYYY-MM-DD.', $member->name];
        }

        if ($row['period'] !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $row['period']) !== 1) {
            return ['The period must be a month written as YYYY-MM.', $member->name];
        }

        $period = BillingPeriod::start($row['period'], $date);

        $held = (int) MemberShareTransaction::query()->where('group_member_id', $member->id)->where('transaction_type', 'purchase')->sum('number_of_shares');

        if ($maximumShares !== null && $held + $shares > $maximumShares) {
            return ["A member can hold at most {$maximumShares} shares (currently {$held}).", $member->name];
        }

        $transaction = $group->memberShareTransactions()->create([
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
        ]);

        $transaction->update(['transaction_number' => sprintf('SHR-%d-%06d', $date->year, $transaction->id)]);

        return [null, $member->name];
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return today();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
        } catch (Throwable) {
            return null;
        }
    }
}
