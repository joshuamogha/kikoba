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
        Schema::create('group_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->nullable()
                ->constrained('groups')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system_role')->default(false);
            $table->string('status', 30)->default('active');

            $table->timestamps();

            $table->unique(['group_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_roles');
    }
};
