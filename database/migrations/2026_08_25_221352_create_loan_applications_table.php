<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->restrictOnDelete();

            $table->foreignId('financial_cycle_id')
                ->constrained('financial_cycles')
                ->restrictOnDelete();

            $table->foreignId('loan_product_id')
                ->constrained('loan_products')
                ->restrictOnDelete();

            $table->foreignId('group_member_id')
                ->constrained('group_members')
                ->restrictOnDelete();

            $table->string('application_number', 50)->unique();

            $table->decimal('requested_amount', 18, 2);
            $table->decimal('approved_amount', 18, 2)->nullable();

            $table->text('purpose');
            $table->unsignedInteger('duration_months');

            $table->decimal('interest_rate', 8, 4);
            $table->foreignId('loan_interest_method_id')
                ->constrained('loan_interest_methods')
                ->restrictOnDelete();

            $table->date('application_date');
            $table->string('status', 30)->default('draft')->index();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

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
        Schema::dropIfExists('loan_applications');
    }
};
