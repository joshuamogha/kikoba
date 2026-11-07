<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->restrictOnDelete();

            $table->foreignId('group_member_id')
                ->nullable()
                ->constrained('group_members')
                ->restrictOnDelete();

            $table->string('transaction_number', 50)->unique();
            $table->string('transaction_type', 50)->index();

            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('TZS');

            $table->string('provider', 50)->nullable();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('phone_number', 20)->nullable();

            /*
             * Polymorphic reference to contribution, repayment,
             * fine, income, expense or share-out.
             */
            $table->nullableMorphs('reference');

            $table->string('status', 30)->default('pending')->index();
            $table->text('failure_reason')->nullable();

            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'group_id',
                'transaction_type',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
