<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->restrictOnDelete();

            $table->foreignId('financial_cycle_id')
                ->constrained('financial_cycles')
                ->restrictOnDelete();

            $table->foreignId('group_fund_id')
                ->constrained('group_funds')
                ->restrictOnDelete();

            $table->string('expense_number', 50)->unique();
            $table->foreignId('group_expense_category_id')
                ->constrained('group_expense_categories')
                ->restrictOnDelete();

            $table->date('expense_date');
            $table->text('description');
            $table->decimal('amount', 18, 2);

            $table->string('payee_name');
            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->restrictOnDelete();
            $table->string('external_reference')->nullable();

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index([
                'group_id',
                'financial_cycle_id',
                'expense_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_expenses');
    }
};
