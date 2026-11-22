<?php

namespace Database\Seeders;

use App\Models\SettingManagement\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Create the one site settings row, with the platform's default brand colours.
     */
    public function run(): void
    {
        SiteSetting::query()->firstOrCreate([], [
            ...SiteSetting::COLORS,
            'name' => config('app.name'),
            'motto' => 'Your savings group, in one place',
            'noindex_dashboard' => true,
        ]);
    }
}
