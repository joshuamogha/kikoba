<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_fines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->restrictOnDelete();

            $table->foreignId('financial_cycle_id')
                ->constrained('financial_cycles')
                ->restrictOnDelete();

            $table->foreignId('group_member_id')
                ->constrained('group_members')
                ->restrictOnDelete();

            $table->foreignId('fine_type_id')
                ->constrained('fine_types')
                ->restrictOnDelete();

            $table->string('fine_number', 50)->unique();
            $table->text('reason');

            $table->date('fine_date');
            $table->date('due_date')->nullable();

            $table->decimal('amount', 18, 2);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('balance', 18, 2);

            $table->string('status', 30)->default('pending')->index();

            $table->foreignId('issued_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('waiver_reason')->nullable();

            $table->timestamps();

            $table->index([
                'group_member_id',
                'financial_cycle_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_fines');
    }
};
