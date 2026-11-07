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
        Schema::table('group_members', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
            $table->string('phone_number', 20)->nullable()->after('name');
        });

        // Existing members all have an account: copy their name and phone onto the membership.
        DB::table('group_members')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->each(function ($member) {
                $user = DB::table('users')->where('id', $member->user_id)->first(['name', 'phone_number']);

                DB::table('group_members')->where('id', $member->id)->update([
                    'name' => $user->name,
                    'phone_number' => $user->phone_number,
                ]);
            });

        Schema::table('group_members', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('phone_number', 20)->nullable(false)->change();
            $table->foreignId('user_id')->nullable()->change();
            $table->unique(['group_id', 'phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'phone_number']);
            $table->dropColumn(['name', 'phone_number']);
        });
    }
};
