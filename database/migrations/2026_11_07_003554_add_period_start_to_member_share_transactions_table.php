<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('member_share_transactions', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('transaction_date');

            $table->index(['group_member_id', 'period_start'], 'member_share_txn_member_period_index');
        });

        // Existing share purchases are for the month they were made in; contribution items recorded without a period likewise.
        DB::table('member_share_transactions')->whereNull('period_start')->orderBy('id')->each(function ($row): void {
            DB::table('member_share_transactions')->where('id', $row->id)->update([
                'period_start' => substr($row->transaction_date, 0, 7).'-01',
            ]);
        });

        DB::table('contribution_items')->whereNull('period_start')->orderBy('id')->each(function ($item): void {
            $date = DB::table('contributions')->where('id', $item->contribution_id)->value('contribution_date');

            DB::table('contribution_items')->where('id', $item->id)->update([
                'period_start' => substr($date, 0, 7).'-01',
                'period_end' => date('Y-m-t', strtotime(substr($date, 0, 7).'-01')),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_share_transactions', function (Blueprint $table) {
            $table->dropIndex('member_share_txn_member_period_index');
            $table->dropColumn('period_start');
        });
    }
};
