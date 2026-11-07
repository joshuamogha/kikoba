<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_incomes', function (Blueprint $table) {
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

            $table->string('income_number', 50)->unique();
            $table->foreignId('income_category_id')
                ->constrained('income_categories')
                ->restrictOnDelete();

            $table->date('income_date');
            $table->text('description');
            $table->decimal('amount', 18, 2);

            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();
            $table->string('external_reference')->nullable();
            $table->string('status', 30)->default('pending')->index();

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index([
                'group_id',
                'financial_cycle_id',
                'income_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_incomes');
    }
};
