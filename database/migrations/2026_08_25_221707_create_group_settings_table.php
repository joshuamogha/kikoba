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
        Schema::create('group_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->unique()
                ->constrained('groups')
                ->cascadeOnDelete();
            $table->foreignId('contribution_frequency_id')
                ->constrained('contribution_frequencies')
                ->restrictOnDelete();
            $table->foreignId('loan_interest_method_id')
                ->constrained('loan_interest_methods')
                ->restrictOnDelete();
            $table->foreignId('penalty_type_id')
                ->constrained('penalty_types')
                ->restrictOnDelete();
            $table->decimal('minimum_contribution', 18, 2)->default(0);
            $table->decimal('maximum_contribution', 18, 2)->nullable();
            $table->decimal('share_price', 18, 2)->default(0);
            $table->unsignedInteger('minimum_shares')->default(0);
            $table->unsignedInteger('maximum_shares')->nullable();
            $table->decimal('default_loan_interest_rate', 8, 4)->default(0);
            $table->decimal('maximum_loan_multiplier', 8, 2)->default(3);
            $table->unsignedInteger('default_loan_duration_months')->default(3);
            $table->unsignedInteger('loan_grace_period_days')->default(0);
            $table->decimal('late_payment_penalty_value', 18, 2)->default(0);
            $table->boolean('loan_guarantors_required')->default(0);
            $table->boolean('withdrawal_approvals_required')->default(0);
            $table->boolean('allow_partial_contributions')->default(true);
            $table->boolean('allow_multiple_loans')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_settings');
    }
};
