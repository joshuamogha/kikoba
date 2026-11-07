<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;

trait SeedsGroupData
{
    /**
     * The fixed "today" used when generating payment histories.
     */
    protected const TODAY = '2026-11-05';

    /**
     * Relative frequency of each payment method code in generated payments.
     *
     * @var array<string, int>
     */
    protected const PAYMENT_WEIGHTS = [
        'CASH' => 20,
        'MPESA' => 40,
        'TIGOPESA' => 20,
        'AIRTELMONEY' => 10,
        'HALOPESA' => 5,
        'BANK' => 5,
    ];

    /**
     * Add created_at / updated_at to a row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function stamp(array $row): array
    {
        $now = now();

        return $row + ['created_at' => $now, 'updated_at' => $now];
    }

    /**
     * Short group code taken from the registration number, e.g. VIC/KND/2021/0142 => KND.
     */
    protected function groupCode(object $group): string
    {
        return explode('/', $group->registration_number)[1];
    }

    protected function lookupId(string $table, int $groupId, string $column, string $value): int
    {
        return (int) DB::table($table)
            ->where('group_id', $groupId)
            ->where($column, $value)
            ->value('id');
    }

    /**
     * Payment method ids of a group keyed by code.
     *
     * @return array<string, int>
     */
    protected function paymentMethodIds(int $groupId): array
    {
        return DB::table('payment_methods')
            ->where('group_id', $groupId)
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Pick a payment method code using the weights above.
     */
    protected function pickPaymentMethodCode(): string
    {
        $roll = mt_rand(1, array_sum(self::PAYMENT_WEIGHTS));

        foreach (self::PAYMENT_WEIGHTS as $code => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $code;
            }
        }

        return 'CASH';
    }

    /**
     * Generate a mobile-money style transaction reference, e.g. RK31ABCD9X.
     */
    protected function mobileReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $reference = '';

        for ($i = 0; $i < 10; $i++) {
            $reference .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
        }

        return $reference;
    }

    /**
     * User id of the member currently holding a leadership role in a group.
     */
    protected function leaderUserId(int $groupId, string $roleName): int
    {
        return (int) DB::table('group_member_roles')
            ->join('group_roles', 'group_roles.id', '=', 'group_member_roles.group_role_id')
            ->join('group_members', 'group_members.id', '=', 'group_member_roles.group_member_id')
            ->where('group_members.group_id', $groupId)
            ->where('group_roles.name', $roleName)
            ->value('group_members.user_id');
    }

    /**
     * Reference and method for a payment: cash has no reference, the rest do.
     *
     * @param  array<string, int>  $methodIds
     * @return array{0: int, 1: string|null, 2: string}
     */
    protected function randomPayment(array $methodIds): array
    {
        $code = $this->pickPaymentMethodCode();

        return [$methodIds[$code], $code === 'CASH' ? null : $this->mobileReference(), $code];
    }
}
