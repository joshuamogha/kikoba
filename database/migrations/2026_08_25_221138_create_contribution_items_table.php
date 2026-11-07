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
        Schema::create('contribution_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contribution_id')
                ->constrained('contributions')
                ->cascadeOnDelete();

            $table->foreignId('group_fund_id')
                ->constrained('group_funds')
                ->restrictOnDelete();

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['contribution_id', 'group_fund_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_items');
    }
};
