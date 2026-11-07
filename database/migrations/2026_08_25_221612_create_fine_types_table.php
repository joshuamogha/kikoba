<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fine_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();

            $table->decimal('default_amount', 18, 2)->default(0);
            $table->string('calculation_type', 30)->default('fixed');

            $table->string('status', 30)->default('active')->index();

            $table->timestamps();

            $table->unique(['group_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_types');
    }
};
