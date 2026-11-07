<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FineSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed fines for missed contributions and meeting offences, and their payments.
     */
    public function run(): void
    {
        mt_srand(2028);

        $today = Carbon::parse(self::TODAY);

        foreach (DB::table('groups')->orderBy('id')->get() as $groupIndex => $group) {
            $code = $this->groupCode($group);
            $methodIds = $this->paymentMethodIds($group->id);
            $treasurerId = $this->leaderUserId($group->id, 'Treasurer');
            $secretaryId = $this->leaderUserId($group->id, 'Secretary');
            $chairId = $this->leaderUserId($group->id, 'Chairperson');
            $members = DB::table('group_members')->where('group_id', $group->id)->orderBy('id')->get();
            $meetingDay = 5 + 5 * $groupIndex;
            $fineSequence = 0;
            $paymentSequence = 0;

            $fines = [];
            $waiverGiven = false;

            // A fine for every month a member who had already joined did not contribute.
            foreach (DB::table('financial_cycles')->where('group_id', $group->id)->orderBy('cycle_number')->get() as $cycle) {
                $year = (int) substr($cycle->start_date, 0, 4);

                for ($month = 1; $month <= ($year === 2025 ? 12 : 10); $month++) {
                    $periodStart = Carbon::create($year, $month, 1);

                    foreach ($members as $member) {
                        if (Carbon::parse($member->joined_at)->gt($periodStart->copy()->endOfMonth())) {
                            continue;
                        }

                        $contributed = DB::table('contributions')
                            ->where('financial_cycle_id', $cycle->id)
                            ->where('group_member_id', $member->id)
                            ->whereBetween('contribution_date', [$periodStart->toDateString(), $periodStart->copy()->endOfMonth()->toDateString()])
                            ->exists();

                        if (! $contributed) {
                            $fines[] = [
                                $cycle->id, $member->id, 'MISSED_CONTRIB',
                                'Hakuchangia mchango wa '.$periodStart->format('F Y').'.',
                                $periodStart->copy()->day(min($meetingDay, $periodStart->daysInMonth))->addDays(7),
                            ];
                        }
                    }
                }
            }

            // A few meeting offences.
            $cycle2025 = DB::table('financial_cycles')->where('group_id', $group->id)->where('cycle_number', 1)->value('id');
            $cycle2026 = DB::table('financial_cycles')->where('group_id', $group->id)->where('cycle_number', 2)->value('id');
            $fines[] = [$cycle2025, $members[3]->id, 'LATE_MEETING', 'Alichelewa kikao cha Juni kwa zaidi ya saa moja.', Carbon::parse('2025-06-14')];
            $fines[] = [$cycle2026, $members[8]->id, 'ABSENT_MEETING', 'Alikosa kikao cha Agosti bila taarifa.', Carbon::parse('2026-08-09')];
            $fines[] = [$cycle2026, $members[6]->id, 'LATE_MEETING', 'Alichelewa kikao cha Oktoba.', Carbon::parse('2026-10-10')];

            foreach ($fines as $index => [$cycleId, $memberId, $fineTypeCode, $reason, $fineDate]) {
                $fineType = DB::table('fine_types')->where('group_id', $group->id)->where('code', $fineTypeCode)->first();
                $amount = (int) $fineType->default_amount;
                $isWaived = ! $waiverGiven
                    && $fineTypeCode === 'MISSED_CONTRIB'
                    && $fineDate->between(Carbon::parse('2026-01-01'), Carbon::parse('2026-08-31'));
                $waiverGiven = $waiverGiven || $isWaived;
                $isRecent = $fineDate->gte(Carbon::parse('2026-09-15'));

                $paid = match (true) {
                    $isWaived => 0,
                    $isRecent && $index % 2 === 0 => 0,
                    $isRecent => (int) ($amount / 2),
                    default => $amount,
                };

                $status = match (true) {
                    $isWaived => 'waived',
                    $paid === $amount => 'paid',
                    $paid === 0 => 'pending',
                    default => 'partial',
                };

                $fineId = DB::table('member_fines')->insertGetId($this->stamp([
                    'group_id' => $group->id,
                    'financial_cycle_id' => $cycleId,
                    'group_member_id' => $memberId,
                    'fine_type_id' => $fineType->id,
                    'fine_number' => sprintf('%s-FN-%s-%04d', $code, $fineDate->format('Y'), ++$fineSequence),
                    'reason' => $reason,
                    'fine_date' => $fineDate->toDateString(),
                    'due_date' => $fineDate->copy()->addDays(14)->toDateString(),
                    'amount' => $amount,
                    'amount_paid' => $paid,
                    'balance' => $isWaived ? 0 : $amount - $paid,
                    'status' => $status,
                    'issued_by' => $secretaryId,
                    'approved_by' => $chairId,
                    'approved_at' => $fineDate->copy()->addDay()->setTime(10, 0),
                    'waiver_reason' => $isWaived ? 'Alikuwa mgonjwa, kamati imemsamehe.' : null,
                ]));

                if ($paid === 0) {
                    continue;
                }

                $paymentDate = $fineDate->copy()->addDays(mt_rand(2, 6))->min($today);
                [$methodId, $reference] = $this->randomPayment($methodIds);

                DB::table('fine_payments')->insert($this->stamp([
                    'member_fine_id' => $fineId,
                    'payment_number' => sprintf('%s-FP-%s-%04d', $code, $fineDate->format('Y'), ++$paymentSequence),
                    'payment_date' => $paymentDate->toDateString(),
                    'amount' => $paid,
                    'payment_method_id' => $methodId,
                    'external_reference' => $reference,
                    'status' => 'approved',
                    'received_by' => $treasurerId,
                ]));
            }
        }
    }
}
