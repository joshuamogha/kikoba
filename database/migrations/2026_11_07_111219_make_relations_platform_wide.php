<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relations (spouse, parent, ...) are shared by every group, so they stop belonging to one group.
     */
    public function up(): void
    {
        DB::table('relations')->orderByDesc('id')->get()->groupBy('name')->each(function ($rows): void {
            $rows->skip(1)->each(fn ($row) => DB::table('relations')->where('id', $row->id)->delete());
        });

        Schema::table('relations', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropUnique(['group_id', 'name']);
            $table->dropColumn('group_id');
        });

        Schema::table('relations', function (Blueprint $table) {
            $table->unique('name');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('relations', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->unique(['group_id', 'name']);
        });
    }
};
