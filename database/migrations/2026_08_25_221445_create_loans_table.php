<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->restrictOnDelete();

            $table->foreignId('financial_cycle_id')
                ->constrained('financial_cycles')
                ->restrictOnDelete();

            $table->foreignId('loan_application_id')
                ->unique()
                ->constrained('loan_applications')
                ->restrictOnDelete();

            $table->foreignId('group_member_id')
                ->constrained('group_members')
                ->restrictOnDelete();

            $table->foreignId('loan_product_id')
                ->constrained('loan_products')
                ->restrictOnDelete();

            $table->string('loan_number', 50)->unique();

            $table->decimal('principal_amount', 18, 2);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);

            $table->decimal('total_payable', 18, 2);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('outstanding_balance', 18, 2);

            $table->date('disbursement_date');
            $table->date('first_payment_date');
            $table->date('maturity_date');

            $table->decimal('interest_rate', 8, 4);
            $table->foreignId('loan_interest_method_id')
                ->constrained('loan_interest_methods')
                ->restrictOnDelete();

            $table->string('status', 30)->default('active')->index();

            $table->timestamps();

            $table->index([
                'group_id',
                'group_member_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
