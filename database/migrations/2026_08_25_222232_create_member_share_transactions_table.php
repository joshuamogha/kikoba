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
        Schema::create('member_share_transactions', function (Blueprint $table) {
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
            $table->string('transaction_number', 50)->unique();
            $table->string('transaction_type', 50)->index();
            $table->unsignedInteger('number_of_shares');
            $table->decimal('price_per_share', 18, 2);
            $table->decimal('total_amount', 18, 2);
            $table->date('transaction_date');
            $table->string('status', 30)->default('pending')->index();
            $table->string('reference')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['group_id', 'financial_cycle_id', 'transaction_date'], 'member_share_txn_group_cycle_date_index');
            $table->index(['group_member_id', 'financial_cycle_id', 'status'], 'member_share_txn_member_cycle_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_share_transactions');
    }
};
