<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_outs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->restrictOnDelete();

            $table->foreignId('financial_cycle_id')
                ->unique()
                ->constrained('financial_cycles')
                ->restrictOnDelete();

            $table->string('share_out_number', 50)->unique();
            $table->date('calculation_date');

            $table->decimal('total_savings', 18, 2)->default(0);
            $table->decimal('total_share_value', 18, 2)->default(0);

            $table->decimal('total_interest_income', 18, 2)->default(0);
            $table->decimal('total_other_income', 18, 2)->default(0);
            $table->decimal('total_fine_income', 18, 2)->default(0);

            $table->decimal('total_expenses', 18, 2)->default(0);
            $table->decimal('distributable_profit', 18, 2)->default(0);
            $table->decimal('total_distributable', 18, 2)->default(0);

            $table->string('status', 30)->default('draft')->index();

            $table->foreignId('calculated_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_outs');
    }
};
