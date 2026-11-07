<?php

namespace Database\Seeders;

use App\Actions\UserManagement\CreateGroup;
use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupMemberSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed system roles, group members and their leadership roles.
     */
    public function run(): void
    {
        $adminId = DB::table('users')->where('email', 'admin@kibubu.co.tz')->value('id');

        $rolesByName = app(CreateGroup::class)->ensureSystemRoles();

        // [user name, membership type, joined on, leadership role]
        $membersByGroup = [
            'VIC/KND/2021/0142' => [
                ['Neema Mushi', 'Founder Member', '2021-03-14', 'Chairperson'],
                ['Asha Juma', 'Founder Member', '2021-03-14', 'Secretary'],
                ['Mariam Hassan', 'Founder Member', '2021-03-14', 'Treasurer'],
                ['Grace Mwakalinga', 'Ordinary Member', '2022-02-12', null],
                ['Fatuma Salim', 'Founder Member', '2021-03-14', 'Vice Chairperson'],
                ['Rehema Kileo', 'Ordinary Member', '2022-06-11', null],
                ['Zainab Mrisho', 'Ordinary Member', '2023-01-14', null],
                ['Upendo Massawe', 'Ordinary Member', '2023-05-13', null],
                ['Halima Bakari', 'Ordinary Member', '2023-09-09', null],
                ['Esther Shayo', 'Associate Member', '2024-02-10', null],
                ['Joyce Ndunguru', 'Ordinary Member', '2024-07-13', null],
                ['Amina Said', 'Ordinary Member', '2026-03-07', null],
            ],
            'SAC/ARU/2019/0087' => [
                ['Baraka Mollel', 'Founder Member', '2019-08-20', 'Chairperson'],
                ['Joseph Kimaro', 'Founder Member', '2019-08-20', 'Treasurer'],
                ['Happiness Urio', 'Founder Member', '2019-08-20', 'Secretary'],
                ['Emmanuel Lyimo', 'Ordinary Member', '2020-03-14', 'Vice Chairperson'],
                ['Daniel Mushi', 'Ordinary Member', '2021-01-16', null],
                ['Peter Swai', 'Ordinary Member', '2021-08-14', null],
                ['Elizabeth Mbise', 'Ordinary Member', '2022-04-09', null],
                ['Godfrey Temba', 'Ordinary Member', '2023-02-11', null],
                ['Rose Kessy', 'Associate Member', '2024-05-18', null],
                ['Samson Laizer', 'Ordinary Member', '2026-03-14', null],
            ],
        ];

        foreach (DB::table('groups')->orderBy('id')->get() as $group) {
            $code = $this->groupCode($group);

            foreach ($membersByGroup[$group->registration_number] as $index => [$name, $type, $joinedAt, $leadership]) {
                $user = DB::table('users')->where('name', $name)->first(['id', 'name', 'phone_number']);

                $memberId = DB::table('group_members')->insertGetId($this->stamp([
                    'group_id' => $group->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'phone_number' => $user->phone_number,
                    'membership_type_id' => $this->lookupId('membership_types', $group->id, 'name', $type),
                    'member_number' => sprintf('%s-%03d', $code, $index + 1),
                    'joined_at' => $joinedAt,
                    'membership_status' => 'active',
                    'approved_at' => $joinedAt.' 10:00:00',
                    'approved_by' => $adminId,
                ]));

                $assignments = [['Member', $joinedAt]];

                if ($leadership !== null) {
                    $assignments[] = [$leadership, '2025-01-01'];
                }

                foreach ($assignments as [$roleName, $startsAt]) {
                    DB::table('group_member_roles')->insert($this->stamp([
                        'group_member_id' => $memberId,
                        'group_role_id' => $rolesByName[$roleName],
                        'starts_at' => $startsAt,
                        'ends_at' => null,
                        'status' => 'active',
                        'assigned_by' => $adminId,
                    ]));
                }
            }
        }
    }
}
