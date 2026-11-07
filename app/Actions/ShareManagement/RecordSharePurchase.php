<?php

namespace App\Actions\ShareManagement;

use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordSharePurchase
{
    /**
     * How many months a purchase may be carried forward when the monthly maximum is full.
     */
    public const MAX_MONTHS = 12;

    public function __construct(private ResolveFinancialCycle $resolveCycle) {}

    /**
     * How many shares fit in each month, starting with $startPeriod. When the member's monthly maximum is reached, the rest of the
     * shares move to the next month, and so on. Nothing is recorded here.
     *
     * @return array<int, array{period: Carbon, shares: int}>
     *
     * @throws InvalidArgumentException when the shares do not fit in the next MAX_MONTHS months
     */
    public function allocate(GroupMember $member, int $shares, Carbon $startPeriod, ?int $maximumPerMonth): array
    {
        if ($maximumPerMonth === null) {
            return [['period' => $startPeriod->copy(), 'shares' => $shares]];
        }

        $allocations = [];
        $period = $startPeriod->copy();
        $remaining = $shares;

        for ($month = 0; $month < self::MAX_MONTHS && $remaining > 0; $month++) {
            $take = min($remaining, $maximumPerMonth - $this->heldIn($member, $period));

            if ($take > 0) {
                $allocations[] = ['period' => $period->copy(), 'shares' => $take];
                $remaining -= $take;
            }

            $period->addMonthNoOverflow();
        }

        if ($remaining > 0) {
            throw new InvalidArgumentException('There is no room for these shares in the next '.self::MAX_MONTHS.' months.');
        }

        return $allocations;
    }

    /**
     * Record an approved purchase, one record per month it is spread over. $createdAt, when given, becomes the created and updated time.
     *
     * @return array<int, MemberShareTransaction>
     *
     * @throws InvalidArgumentException when the shares do not fit in the next MAX_MONTHS months
     */
    public function handle(Group $group, GroupMember $member, User $approver, int $shares, Carbon $date, Carbon $startPeriod, ?string $reference, float $price, ?int $maximumPerMonth, ?Carbon $createdAt = null): array
    {
        $allocations = $this->allocate($member, $shares, $startPeriod, $maximumPerMonth);

        return DB::transaction(function () use ($group, $member, $approver, $allocations, $date, $reference, $price, $createdAt): array {
            $records = [];

            foreach ($allocations as ['period' => $period, 'shares' => $count]) {
                $attributes = [
                    'financial_cycle_id' => $this->resolveCycle->handle($group, $period, $approver->id)->id,
                    'group_member_id' => $member->id,
                    'transaction_number' => 'PENDING-'.uniqid(),
                    'transaction_type' => 'purchase',
                    'number_of_shares' => $count,
                    'price_per_share' => $price,
                    'total_amount' => round($count * $price, 2),
                    'transaction_date' => $date->toDateString(),
                    'period_start' => $period->toDateString(),
                    'status' => 'approved',
                    'reference' => $reference,
                    'approved_by' => $approver->id,
                ];

                $transaction = $group->memberShareTransactions()->forceCreate($createdAt === null ? $attributes : [...$attributes, 'created_at' => $createdAt, 'updated_at' => $createdAt]);

                $transaction->withoutTimestamps(fn () => $transaction->update(['transaction_number' => sprintf('SHR-%d-%06d', $date->year, $transaction->id)]));

                $records[] = $transaction;
            }

            return $records;
        });
    }

    /**
     * A short text such as "2 in January 2026, 5 in February 2026".
     *
     * @param  array<int, MemberShareTransaction>  $records
     */
    public static function describe(array $records): string
    {
        return collect($records)->map(fn (MemberShareTransaction $record) => $record->number_of_shares.' in '.$record->period_start->format('F Y'))->implode(', ');
    }

    private function heldIn(GroupMember $member, Carbon $period): int
    {
        return (int) MemberShareTransaction::query()
            ->where('group_member_id', $member->id)
            ->where('transaction_type', 'purchase')
            ->whereDate('period_start', $period->toDateString())
            ->sum('number_of_shares');
    }
}
