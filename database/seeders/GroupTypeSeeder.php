<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupTypeSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed the group types found in Tanzania.
     */
    public function run(): void
    {
        $types = [
            ['VICOBA', 'Village Community Banks: member-owned savings and loan groups.'],
            ['SACCOS', 'Savings and Credit Cooperative Societies registered with the cooperative registrar.'],
            ['Upatu (ROSCA)', 'Rotating savings groups where members take turns to receive the pooled contribution.'],
            ['Burial Society (Chama cha Msiba)', 'Mutual welfare groups that support members during bereavement.'],
            ['Youth Group (Kikundi cha Vijana)', 'Youth economic empowerment groups.'],
            ['Faith-Based Welfare Group', 'Church and mosque welfare and savings groups.'],
        ];

        foreach ($types as $index => [$name, $description]) {
            DB::table('group_types')->insert($this->stamp([
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'display_order' => $index + 1,
            ]));
        }
    }
}
