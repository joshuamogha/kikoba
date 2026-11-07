<?php

namespace App\Console\Commands;

use App\Actions\ShareManagement\RecordSharePurchase;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shares:link-contributions {group? : The id of one group; every group when omitted}')]
#[Description('Record a contribution (Shares fund) for every approved share purchase that has none yet')]
class LinkShareContributionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecordSharePurchase $recordPurchase): int
    {
        $linked = 0;

        MemberShareTransaction::query()
            ->whereNull('contribution_id')
            ->where('status', 'approved')
            ->where('transaction_type', 'purchase')
            ->when($this->argument('group'), fn ($query, $id) => $query->where('group_id', $id))
            ->with('group')
            ->orderBy('id')
            ->each(function (MemberShareTransaction $purchase) use ($recordPurchase, &$linked): void {
                $approver = User::query()->find($purchase->approved_by ?? $purchase->group->created_by);

                if ($approver !== null && $recordPurchase->recordContribution($purchase, $approver, $purchase->created_at) !== null) {
                    $linked++;
                }
            });

        $this->info("{$linked} share purchase(s) now have a contribution.");

        return self::SUCCESS;
    }
}
