<?php

namespace App\Actions\UserManagement;

use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\GroupMemberRole;
use App\Models\UserManagement\GroupRole;
use App\Models\UserManagement\User;
use Illuminate\Support\Facades\DB;

class CreateGroup
{
    /**
     * Create a group, its default lookups, and make the creator its founding Chairperson.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $creator, array $data): Group
    {
        return DB::transaction(function () use ($creator, $data): Group {
            $group = Group::query()->create([
                ...$data,
                'currency' => $data['currency'] ?? 'TZS',
                'status' => 'active',
                'created_by' => $creator->id,
            ]);

            $this->provisionLookups($group->id);
            $group->ensureGroupSetting();

            $roles = $this->ensureSystemRoles();
            $founderTypeId = DB::table('membership_types')
                ->where('group_id', $group->id)
                ->where('name', 'Founder Member')
                ->value('id');

            $member = GroupMember::query()->create([
                'group_id' => $group->id,
                'user_id' => $creator->id,
                'name' => $creator->name,
                'phone_number' => $creator->phone_number,
                'membership_type_id' => $founderTypeId,
                'member_number' => $group->nextMemberNumber(),
                'joined_at' => now()->toDateString(),
                'membership_status' => 'active',
                'approved_at' => now(),
                'approved_by' => $creator->id,
            ]);

            foreach ([GroupRole::MEMBER, GroupRole::CHAIRPERSON] as $roleName) {
                GroupMemberRole::query()->create([
                    'group_member_id' => $member->id,
                    'group_role_id' => $roles[$roleName],
                    'starts_at' => now()->toDateString(),
                    'status' => 'active',
                    'assigned_by' => $creator->id,
                ]);
            }

            return $group;
        });
    }

    /**
     * Insert the default rows of every per-group lookup table (see config/group_defaults.php).
     */
    public function provisionLookups(int $groupId): void
    {
        $now = now();

        foreach (config('group_defaults.lookups') as $table => $rows) {
            foreach ($rows as $index => $row) {
                DB::table($table)->insert([
                    'group_id' => $groupId,
                    'description' => null,
                    ...$row,
                    'display_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Make sure the shared system roles exist, returning their ids keyed by name.
     *
     * @return array<string, int>
     */
    public function ensureSystemRoles(): array
    {
        $ids = [];

        foreach (config('group_defaults.system_roles') as $role) {
            $ids[$role['name']] = GroupRole::query()->firstOrCreate(
                ['group_id' => null, 'name' => $role['name']],
                ['description' => $role['description'], 'is_system_role' => true, 'status' => 'active'],
            )->id;
        }

        return $ids;
    }
}
