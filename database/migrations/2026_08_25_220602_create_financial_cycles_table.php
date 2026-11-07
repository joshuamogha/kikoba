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
        Schema::create('financial_cycles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();
            $table->foreignId('contribution_frequency_id')
                ->constrained('contribution_frequencies')
                ->restrictOnDelete();

            $table->string('name');
            $table->unsignedInteger('cycle_number');

            $table->date('start_date');
            $table->date('end_date');

            $table->string('status', 30)->default('draft')->index();

            $table->foreignId('opened_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['group_id', 'cycle_number']);
            $table->index(['group_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_cycles');
    }
};
