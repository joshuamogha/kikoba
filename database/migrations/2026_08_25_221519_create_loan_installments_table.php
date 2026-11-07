<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_id')
                ->constrained('loans')
                ->cascadeOnDelete();

            $table->unsignedInteger('installment_number');
            $table->date('due_date');

            $table->decimal('principal_due', 18, 2);
            $table->decimal('interest_due', 18, 2)->default(0);
            $table->decimal('penalty_due', 18, 2)->default(0);
            $table->decimal('total_due', 18, 2);

            $table->decimal('principal_paid', 18, 2)->default(0);
            $table->decimal('interest_paid', 18, 2)->default(0);
            $table->decimal('penalty_paid', 18, 2)->default(0);
            $table->decimal('total_paid', 18, 2)->default(0);

            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['loan_id', 'due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
