<?php

namespace Database\Seeders;

use App\Models\UserManagement\Relation;
use Illuminate\Database\Seeder;

class RelationSeeder extends Seeder
{
    /**
     * Seed the platform-wide family relations.
     */
    public function run(): void
    {
        foreach (['Spouse', 'Child', 'Parent', 'Sibling', 'Other Relative'] as $index => $name) {
            Relation::query()->firstOrCreate(['name' => $name], ['display_order' => $index + 1, 'is_active' => true]);
        }
    }
}
