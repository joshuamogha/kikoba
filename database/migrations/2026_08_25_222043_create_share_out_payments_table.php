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
        Schema::create('share_out_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_share_out_id')
                ->constrained('member_share_outs')
                ->restrictOnDelete();
            $table->string('payment_number', 50)->unique();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();
            $table->string('external_reference')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['member_share_out_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('share_out_payments');
    }
};
