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
        Schema::table('member_share_transactions', function (Blueprint $table) {
            $table->foreignId('contribution_id')->nullable()->after('group_member_id')->constrained('contributions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_share_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribution_id');
        });
    }
};
