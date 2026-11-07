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
        Schema::create('group_member_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_member_id')
                ->constrained('group_members')
                ->cascadeOnDelete();

            $table->foreignId('group_role_id')
                ->constrained('group_roles')
                ->cascadeOnDelete();

            $table->date('starts_at');
            $table->date('ends_at')->nullable();

            $table->string('status', 30)->default('active')->index();

            $table->foreignId('assigned_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'group_member_id',
                'group_role_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_member_roles');
    }
};
