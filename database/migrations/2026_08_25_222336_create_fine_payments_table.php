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
        Schema::create('fine_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_fine_id')
                ->constrained('member_fines')
                ->restrictOnDelete();
            $table->string('payment_number', 50)->unique();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();
            $table->string('external_reference')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['member_fine_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fine_payments');
    }
};
