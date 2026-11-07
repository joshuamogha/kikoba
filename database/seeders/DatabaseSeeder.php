<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with Tanzanian sample data.
     */
    public function run(): void
    {
        $this->call([
            GroupTypeSeeder::class,
            RelationSeeder::class,
            UserSeeder::class,
            GroupSeeder::class,
            GroupLookupSeeder::class,
            GroupMemberSeeder::class,
            GroupSetupSeeder::class,
            ContributionSeeder::class,
            LoanSeeder::class,
            FineSeeder::class,
            IncomeExpenseSeeder::class,
            ShareOutSeeder::class,
            PaymentTransactionSeeder::class,
        ]);
    }
}
