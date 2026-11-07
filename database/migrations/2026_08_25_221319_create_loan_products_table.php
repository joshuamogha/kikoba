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
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();

            $table->foreignId('group_fund_id')
                ->constrained('group_funds')
                ->restrictOnDelete();

            $table->foreignId('repayment_frequency_id')
                ->constrained('repayment_frequencies')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();

            $table->decimal('minimum_amount', 18, 2)->default(0);
            $table->decimal('maximum_amount', 18, 2)->nullable();

            $table->decimal('maximum_savings_multiplier', 8, 2)
                ->default(3);

            $table->foreignId('loan_interest_method_id')
                ->constrained('loan_interest_methods')
                ->restrictOnDelete();
            $table->decimal('interest_rate', 8, 4);
            $table->string('interest_period', 30)->default('per_loan');

            $table->unsignedInteger('minimum_duration_months')->default(1);
            $table->unsignedInteger('maximum_duration_months');

            $table->unsignedInteger('grace_period_days')->default(0);
            $table->unsignedInteger('guarantors_required')->default(0);

            $table->string('status', 30)->default('active')->index();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['group_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
