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
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('membership_type_id')
                ->constrained('membership_types')
                ->restrictOnDelete();

            $table->string('member_number', 50);
            $table->date('joined_at');

            $table->string('membership_status', 30)
                ->default('pending')
                ->index();

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->date('left_at')->nullable();
            $table->text('exit_reason')->nullable();

            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->unique(['group_id', 'member_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
