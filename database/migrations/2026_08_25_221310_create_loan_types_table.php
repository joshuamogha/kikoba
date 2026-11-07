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
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamps();
            $table->unique(['group_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_types');
    }
};
