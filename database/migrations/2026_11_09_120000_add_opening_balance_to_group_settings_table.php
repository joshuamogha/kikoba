<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('group_settings', function (Blueprint $table) {
            $table->decimal('opening_balance', 18, 2)->default(0)->after('withdrawal_approvals_required');
            $table->date('opening_balance_date')->nullable()->after('opening_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_settings', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'opening_balance_date']);
        });
    }
};
