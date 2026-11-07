<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed two sample groups, one VICOBA in Dar es Salaam and one SACCOS in Arusha.
     */
    public function run(): void
    {
        $adminId = DB::table('users')->where('email', 'admin@kibubu.co.tz')->value('id');

        $groups = [
            [
                'type' => 'VICOBA',
                'name' => 'Umoja wa Wanawake Kinondoni VICOBA',
                'description' => 'Kikundi cha akiba na mikopo cha wanawake wa Kinondoni, Dar es Salaam.',
                'registration_number' => 'VIC/KND/2021/0142',
                'established_date' => '2021-03-14',
                'maximum_members' => 30,
            ],
            [
                'type' => 'SACCOS',
                'name' => 'Tumaini SACCOS Arusha',
                'description' => 'Chama cha akiba na mikopo cha wakulima na wafanyabiashara wadogo wa Arusha.',
                'registration_number' => 'SAC/ARU/2019/0087',
                'established_date' => '2019-08-20',
                'maximum_members' => 100,
            ],
        ];

        foreach ($groups as $group) {
            DB::table('groups')->insert($this->stamp([
                'group_type_id' => DB::table('group_types')->where('name', $group['type'])->value('id'),
                'name' => $group['name'],
                'description' => $group['description'],
                'registration_number' => $group['registration_number'],
                'established_date' => $group['established_date'],
                'currency' => 'TZS',
                'maximum_members' => $group['maximum_members'],
                'status' => 'active',
                'created_by' => $adminId,
            ]));
        }
    }
}
