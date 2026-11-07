<?php

namespace App\Actions\LoanManagement;

use App\Actions\Concerns\ReadsCsvUpload;
use App\Actions\PaymentManagement\RecordPaymentTransaction;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\PaymentMethod;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use App\Support\ImportDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportLoanRepayments
{
    use ReadsCsvUpload;

    public const MAX_ROWS = 500;

    /**
     * @var array<int, string>
     */
    public const REQUIRED = ['loan_number', 'amount', 'payment_date'];

    /**
     * @var array<int, string>
     */
    public const OPTIONAL = ['receipt'];

    public function __construct(private AllocateLoanRepayment $allocate, private RecordPaymentTransaction $recordPayment) {}

    /**
     * Record loan repayments from a CSV or Excel file. Each row names the loan by its loan number, the amount paid and the date.
     * Rows are applied in payment-date order, so older payments pay older installments even when the file is not sorted. Every
     * row becomes an approved repayment that is allocated to the loan's installments (penalty, interest, then principal) and logged
     * as a payment transaction, exactly like approving a repayment by hand. Rows that cannot be recorded, or were recorded before,
     * are skipped and reported.
     *
     * @return array{imported: int, skipped: array<int, array{row: int, name: string, phone: string, reason: string}>}
     *
     * @throws InvalidArgumentException when the file is not a usable import file
     */
    public function handle(Group $group, User $user, UploadedFile $file, PaymentMethod $method): array
    {
        $rows = $this->readCsv($file, self::REQUIRED, self::OPTIONAL, self::MAX_ROWS);

        $ordered = collect($rows)
            ->map(fn (array $row, int $line) => ['line' => $line, 'row' => $row, 'date' => ImportDate::parse($row['payment_date'])])
            ->sortBy(fn (array $item) => [$item['date']?->getTimestamp() ?? PHP_INT_MAX, $item['line']])
            ->values();

        return DB::transaction(function () use ($group, $user, $method, $ordered): array {
            $imported = 0;
            $skipped = [];

            foreach ($ordered as ['line' => $line, 'row' => $row, 'date' => $date]) {
                [$reason, $name] = $this->record($group, $user, $method, $row, $date);

                if ($reason === null) {
                    $imported++;
                } else {
                    $skipped[] = ['row' => $line, 'name' => $name, 'phone' => $row['loan_number'], 'reason' => $reason];
                }
            }

            usort($skipped, fn (array $a, array $b) => $a['row'] <=> $b['row']);

            return ['imported' => $imported, 'skipped' => $skipped];
        });
    }

    /**
     * Record one row, returning [why it was skipped or null, the member's name].
     *
     * @param  array<string, string>  $row
     * @return array{0: string|null, 1: string}
     */
    private function record(Group $group, User $user, PaymentMethod $method, array $row, ?Carbon $date): array
    {
        $loanNumber = mb_strtolower(trim($row['loan_number']));
        $loan = $loanNumber === '' ? null : $group->loans()->with('groupMember:id,name')->whereRaw('lower(loan_number) = ?', [$loanNumber])->first();

        if ($loan === null) {
            return ['No loan of this group has that loan number.', ''];
        }

        $name = $loan->groupMember->name;

        if ($row['amount'] === '' && $row['payment_date'] === '') {
            return ['No amount and date were entered for this loan.', $name];
        }

        $cents = $this->amountInCents($row['amount']);

        if ($cents === null) {
            return ['The amount must be a positive number.', $name];
        }

        if ($date === null || $date->toDateString() > today()->toDateString()) {
            return ['The payment date is required and must be a past or current date, e.g. 15/12/2025 08:32:47 or 2025-12-15.', $name];
        }

        if ($date->toDateString() < $loan->disbursement_date->toDateString()) {
            return ["The payment date is before the loan was disbursed ({$loan->disbursement_date->toDateString()}).", $name];
        }

        $receipt = $row['receipt'] === '' ? null : mb_substr($row['receipt'], 0, 255);

        if ($receipt !== null && $this->receiptUsed($loan->group_id, $receipt)) {
            return ["Already imported: receipt {$receipt} is already recorded in this group.", $name];
        }

        if ($receipt === null && $this->alreadyImported($loan->id, $date, $cents)) {
            return ['Already imported: this loan already has a repayment with the same date and amount (and no receipt number to tell them apart).', $name];
        }

        if (! in_array($loan->status, ['active', 'overdue'], true)) {
            return ['This loan is already fully paid.', $name];
        }

        if ($cents > (int) round((float) $loan->outstanding_balance * 100)) {
            return ['The amount is more than the loan\'s outstanding balance of '.number_format((float) $loan->outstanding_balance, 2).'.', $name];
        }

        $this->create($user, $method, $loan, $date, $cents / 100, $receipt);

        return [null, $name];
    }

    private function create(User $user, PaymentMethod $method, mixed $loan, Carbon $date, float $amount, ?string $receipt): void
    {
        $repayment = LoanRepayment::query()->forceCreate([
            'loan_id' => $loan->id,
            'group_member_id' => $loan->group_member_id,
            'repayment_number' => 'PENDING-'.uniqid(),
            'payment_date' => $date->toDateString(),
            'amount' => $amount,
            'payment_method_id' => $method->id,
            'external_reference' => $receipt,
            'status' => 'approved',
            'received_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $repayment->withoutTimestamps(function () use ($repayment, $date, $user): void {
            $repayment->update(['repayment_number' => sprintf('LR-%d-%06d', $date->year, $repayment->id)]);
            $this->allocate->handle($repayment);
            $this->recordPayment->handle($repayment, $user);
        });
    }

    /**
     * Whether a repayment with this receipt number is already recorded for a loan of the group.
     */
    private function receiptUsed(int $groupId, string $receipt): bool
    {
        return LoanRepayment::query()
            ->whereHas('loan', fn ($loan) => $loan->where('group_id', $groupId))
            ->whereRaw('lower(trim(external_reference)) = ?', [mb_strtolower(trim($receipt))])
            ->exists();
    }

    /**
     * Without a receipt: whether the loan already has a receipt-less repayment on the date for the amount.
     */
    private function alreadyImported(int $loanId, Carbon $date, int $cents): bool
    {
        return LoanRepayment::query()
            ->where('loan_id', $loanId)
            ->whereDate('payment_date', $date->toDateString())
            ->whereNull('external_reference')
            ->where('amount', number_format($cents / 100, 2, '.', ''))
            ->exists();
    }
}
