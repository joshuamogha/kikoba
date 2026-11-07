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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_type_id')
                ->constrained('group_types')
                ->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('registration_number', 100)
                ->nullable()
                ->unique();

            $table->date('established_date')->nullable();
            $table->char('currency', 3)->default('TZS');
            $table->unsignedInteger('maximum_members')->nullable();

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'group_type_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
