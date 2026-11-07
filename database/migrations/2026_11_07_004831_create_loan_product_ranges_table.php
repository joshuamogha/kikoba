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
        Schema::create('loan_product_ranges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_product_id')
                ->constrained('loan_products')
                ->cascadeOnDelete();
            $table->decimal('minimum_amount', 18, 2)->default(0);
            $table->decimal('maximum_amount', 18, 2)->nullable();
            $table->unsignedInteger('duration_months');

            $table->timestamps();

            $table->index(['loan_product_id', 'minimum_amount']);
        });

        Schema::table('loan_products', function (Blueprint $table) {
            $table->string('repayment_mode', 20)->default('installments')->after('interest_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('repayment_mode');
        });

        Schema::dropIfExists('loan_product_ranges');
    }
};
