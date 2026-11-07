<?php

namespace Database\Seeders;

use App\Actions\UserManagement\CreateGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupLookupSeeder extends Seeder
{
    /**
     * Seed the per-group lookup tables (defined in config/group_defaults.php) for every group.
     */
    public function run(): void
    {
        foreach (DB::table('groups')->orderBy('id')->pluck('id') as $groupId) {
            if (DB::table('membership_types')->where('group_id', $groupId)->exists()) {
                continue; // already provisioned (e.g. a group created through the app)
            }

            app(CreateGroup::class)->provisionLookups($groupId);
        }
    }
}
