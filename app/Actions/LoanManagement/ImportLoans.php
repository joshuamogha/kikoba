<?php

namespace App\Actions\LoanManagement;

use App\Actions\Concerns\ReadsCsvUpload;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use App\Support\ImportDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportLoans
{
    use ReadsCsvUpload;

    public const MAX_ROWS = 500;

    public const MAX_GUARANTORS = 3;

    /**
     * @var array<int, string>
     */
    public const REQUIRED = ['phone_number', 'loan_product', 'amount', 'application_date', 'disbursement_date', 'purpose'];

    public function __construct(private RecordExistingLoan $recordLoan) {}

    /**
     * The guarantor columns of the template, e.g. guarantor1_phone, guarantor1_amount.
     *
     * @return array<int, string>
     */
    public static function guarantorColumns(): array
    {
        return collect(range(1, self::MAX_GUARANTORS))->flatMap(fn (int $number) => ["guarantor{$number}_phone", "guarantor{$number}_amount"])->all();
    }

    /**
     * Record loans from a CSV or Excel file. Each row goes through every step at once: the application, its accepted guarantors
     * (when $includeGuarantors is on), the approval and the disbursement with the installment schedule. Rows that cannot be
     * recorded, or were recorded before, are skipped and reported.
     *
     * @return array{imported: int, skipped: array<int, array{row: int, name: string, phone: string, reason: string}>}
     *
     * @throws InvalidArgumentException when the file is not a usable import file
     */
    public function handle(Group $group, User $user, UploadedFile $file, bool $includeGuarantors): array
    {
        $rows = $this->readCsv($file, self::REQUIRED, self::guarantorColumns(), self::MAX_ROWS);

        return DB::transaction(function () use ($group, $user, $rows, $includeGuarantors): array {
            $imported = 0;
            $skipped = [];

            foreach ($rows as $number => $row) {
                [$reason, $name] = $this->record($group, $user, $row, $includeGuarantors);

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
    private function record(Group $group, User $user, array $row, bool $includeGuarantors): array
    {
        $member = $this->memberByPhone($group, $row['phone_number']);

        if ($member === null) {
            return ['No member of this group has that phone number.', ''];
        }

        if ($member->membership_status !== 'active') {
            return ['The member is not active.', $member->name];
        }

        $product = $group->loanProducts()->where('status', 'active')->where('code', mb_strtoupper(trim($row['loan_product'])))->first();

        if ($product === null) {
            return ['No active loan product of this group has that code.', $member->name];
        }

        $cents = $this->amountInCents($row['amount']);

        if ($cents === null) {
            return ['The amount must be a positive number.', $member->name];
        }

        $amount = $cents / 100;
        $applicationDate = ImportDate::parse($row['application_date'])?->startOfDay();
        $disbursementDate = ImportDate::parse($row['disbursement_date'])?->startOfDay();

        if ($applicationDate === null || $disbursementDate === null || $disbursementDate->isFuture() || $applicationDate->gt($disbursementDate)) {
            return ['The application and disbursement dates are required, must be written like 15/12/2025 or 2025-12-15, and the application date cannot be after the disbursement date or in the future.', $member->name];
        }

        $months = $product->hasRanges() ? $product->termFor($amount) : null;

        if (! $product->hasRanges()) {
            return ["The loan product {$product->code} has no loan ranges, so its term cannot be worked out. Add its ranges on the Loan Ranges page first.", $member->name];
        }

        if ($months === null) {
            return ['No loan range covers this amount.', $member->name];
        }

        if ($row['purpose'] === '') {
            return ['The purpose is required.', $member->name];
        }

        $guarantors = [];

        if ($includeGuarantors) {
            $guarantors = $this->guarantors($group, $member->id, $row);

            if (is_string($guarantors)) {
                return [$guarantors, $member->name];
            }
        }

        if ($this->alreadyImported($group, $member->id, $product->id, $disbursementDate, $cents)) {
            return ['Already imported: this member already has a loan on this product with the same disbursement date and amount.', $member->name];
        }

        $this->recordLoan->handle($group, $product, $user, [
            'group_member_id' => $member->id,
            'requested_amount' => $amount,
            'purpose' => mb_substr($row['purpose'], 0, 1000),
            'guarantors' => $guarantors,
        ], $applicationDate, $disbursementDate);

        return [null, $member->name];
    }

    /**
     * The guarantors listed in the row, or the reason the row cannot be used.
     *
     * @param  array<string, string>  $row
     * @return array<int, array{group_member_id: int, guaranteed_amount: float}>|string
     */
    private function guarantors(Group $group, int $borrowerId, array $row): array|string
    {
        $guarantors = [];

        foreach (range(1, self::MAX_GUARANTORS) as $number) {
            $phone = $row["guarantor{$number}_phone"];
            $amountText = $row["guarantor{$number}_amount"];

            if ($phone === '' && $amountText === '') {
                continue;
            }

            if ($phone === '' || $amountText === '') {
                return "Guarantor {$number} needs both a phone number and an amount.";
            }

            $guarantor = $this->memberByPhone($group, $phone);
            $cents = $this->amountInCents($amountText);

            if ($guarantor === null || $guarantor->membership_status !== 'active') {
                return "Guarantor {$number} is not an active member of this group.";
            }

            if ($guarantor->id === $borrowerId) {
                return 'A member cannot guarantee their own loan.';
            }

            if (isset($guarantors[$guarantor->id])) {
                return "Guarantor {$number} is listed twice.";
            }

            if ($cents === null) {
                return "The amount of guarantor {$number} must be a positive number.";
            }

            $guarantors[$guarantor->id] = ['group_member_id' => $guarantor->id, 'guaranteed_amount' => $cents / 100];
        }

        return array_values($guarantors);
    }

    private function memberByPhone(Group $group, string $rawPhone): mixed
    {
        $phone = $this->cleanPhone($rawPhone);

        return $phone === '' ? null : $group->groupMembers()->where('phone_number', $phone)->first();
    }

    /**
     * Whether the member already has a loan on the product, disbursed on the date, for the amount.
     */
    private function alreadyImported(Group $group, int $memberId, int $productId, Carbon $disbursementDate, int $cents): bool
    {
        return $group->loans()
            ->where('group_member_id', $memberId)
            ->where('loan_product_id', $productId)
            ->whereDate('disbursement_date', $disbursementDate->toDateString())
            ->where('principal_amount', number_format($cents / 100, 2, '.', ''))
            ->exists();
    }
}
