<?php

namespace App\Console\Commands;

use App\Actions\UserManagement\ProvisionGroupDefaults;
use App\Models\UserManagement\Group;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('groups:provision-defaults {group? : The id of one group; every group when omitted}')]
#[Description('Give groups their default settings, funds, cycle, fine types, loan types and loan products where they have none')]
class ProvisionGroupDefaultsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProvisionGroupDefaults $provisionGroupDefaults): int
    {
        $groups = Group::query()
            ->when($this->argument('group'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($groups as $group) {
            $provisionGroupDefaults->handle($group);
        }

        $this->info("Defaults checked for {$groups->count()} group(s).");

        return self::SUCCESS;
    }
}
