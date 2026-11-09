<?php

namespace App\Actions\SettingManagement;

use App\Actions\Concerns\ReadsCsvUpload;
use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Models\SettingManagement\FineType;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\User;
use App\Support\ImportDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportMemberFines
{
    use ReadsCsvUpload;

    public const MAX_ROWS = 500;

    /**
     * @var array<int, string>
     */
    public const REQUIRED = ['phone_number', 'fine_type', 'fine_date', 'reason'];

    /**
     * @var array<int, string>
     */
    public const OPTIONAL = ['amount', 'due_date'];

    public function __construct(private ResolveFinancialCycle $resolveCycle) {}

    /**
     * Issue fines from a CSV or Excel file. Each row names a member by phone number and a fine type by name; the amount defaults
     * to the fine type's default amount. Every row becomes a pending fine. Rows that cannot be issued, or were issued before
     * (same member, fine type, date, amount and reason), are skipped and reported.
     *
     * @return array{imported: int, skipped: array<int, array{row: int, name: string, phone: string, reason: string}>}
     *
     * @throws InvalidArgumentException when the file is not a usable import file
     */
    public function handle(Group $group, User $user, UploadedFile $file): array
    {
        $rows = $this->readCsv($file, self::REQUIRED, self::OPTIONAL, self::MAX_ROWS);
        $types = $group->fineTypes()->where('status', 'active')->get()->keyBy(fn (FineType $type) => mb_strtolower(trim($type->name)));

        return DB::transaction(function () use ($group, $user, $rows, $types): array {
            $imported = 0;
            $skipped = [];

            foreach ($rows as $number => $row) {
                [$reason, $name] = $this->record($group, $user, $row, $types->all());

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
     * Issue one row, returning [why it was skipped or null, the member's name].
     *
     * @param  array<string, string>  $row
     * @param  array<string, FineType>  $types
     * @return array{0: string|null, 1: string}
     */
    private function record(Group $group, User $user, array $row, array $types): array
    {
        $phone = $this->cleanPhone($row['phone_number']);
        $member = $phone === '' ? null : $group->groupMembers()->where('phone_number', $phone)->first();

        if ($member === null) {
            return ['No member of this group has that phone number.', ''];
        }

        if ($member->membership_status !== 'active') {
            return ['The member is not active.', $member->name];
        }

        $type = $types[mb_strtolower(trim($row['fine_type']))] ?? null;

        if ($type === null) {
            return ['No active fine type in this group has that name.', $member->name];
        }

        if ($row['reason'] === '') {
            return ['The reason is required.', $member->name];
        }

        $cents = $row['amount'] === '' ? $this->amountInCents((string) $type->default_amount) : $this->amountInCents($row['amount']);

        if ($cents === null) {
            return [$row['amount'] === '' ? 'The amount is required because the fine type has no default amount.' : 'The amount must be a positive number.', $member->name];
        }

        $date = ImportDate::parse($row['fine_date']);

        if ($date === null || $date->toDateString() > today()->toDateString()) {
            return ['The fine date is required and must be a past or current date, e.g. 15/12/2025 or 2025-12-15.', $member->name];
        }

        $due = null;

        if ($row['due_date'] !== '') {
            $due = ImportDate::parse($row['due_date']);

            if ($due === null || $due->toDateString() < $date->toDateString()) {
                return ['The due date must be a valid date on or after the fine date.', $member->name];
            }
        }

        $reason = mb_substr($row['reason'], 0, 1000);

        if ($this->alreadyImported($group, $member, $type, $date, $cents, $reason)) {
            return ['Already imported: this member already has this fine (same type, date, amount and reason).', $member->name];
        }

        $this->create($group, $user, $member, $type, $date, $due, $cents / 100, $reason);

        return [null, $member->name];
    }

    private function create(Group $group, User $user, GroupMember $member, FineType $type, Carbon $date, ?Carbon $due, float $amount, string $reason): void
    {
        $stamps = ['created_at' => $date, 'updated_at' => $date];

        $fine = $group->memberFines()->forceCreate([
            'group_id' => $group->id,
            'financial_cycle_id' => $this->resolveCycle->handle($group, $date, $user->id)->id,
            'group_member_id' => $member->id,
            'fine_type_id' => $type->id,
            'fine_number' => 'PENDING-'.uniqid(),
            'reason' => $reason,
            'fine_date' => $date->toDateString(),
            'due_date' => $due?->toDateString(),
            'amount' => $amount,
            'amount_paid' => 0,
            'balance' => $amount,
            'status' => 'pending',
            'issued_by' => $user->id,
            ...$stamps,
        ]);

        $fine->withoutTimestamps(fn () => $fine->update(['fine_number' => sprintf('FN-%d-%06d', $date->year, $fine->id)]));
    }

    private function alreadyImported(Group $group, GroupMember $member, FineType $type, Carbon $date, int $cents, string $reason): bool
    {
        return $group->memberFines()
            ->where('group_member_id', $member->id)
            ->where('fine_type_id', $type->id)
            ->whereDate('fine_date', $date->toDateString())
            ->where('amount', number_format($cents / 100, 2, '.', ''))
            ->where('reason', $reason)
            ->exists();
    }
}
