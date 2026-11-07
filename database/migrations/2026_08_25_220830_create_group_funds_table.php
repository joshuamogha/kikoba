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
        Schema::create('group_funds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();
            $table->foreignId('fund_type_id')
                ->constrained('fund_types')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();

            $table->decimal('minimum_contribution', 18, 2)->default(0);
            $table->foreignId('contribution_frequency_id')
                ->nullable()
                ->constrained('contribution_frequencies')
                ->restrictOnDelete();

            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_distributable')->default(false);
            $table->boolean('is_loanable')->default(false);

            $table->string('status', 30)->default('active')->index();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['group_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_funds');
    }
};
