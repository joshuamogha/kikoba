<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')
                ->constrained('loans')
                ->restrictOnDelete();

            $table->foreignId('group_member_id')
                ->constrained('group_members')
                ->restrictOnDelete();

            $table->string('repayment_number', 50)->unique();
            $table->date('payment_date');

            $table->decimal('amount', 18, 2);
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);

            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();
            $table->string('external_reference')->nullable();
            $table->string('status', 30)->default('pending')->index();

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['loan_id', 'payment_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
