<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_application_id')
                ->constrained('loan_applications')
                ->cascadeOnDelete();

            $table->foreignId('group_member_id')
                ->constrained('group_members')
                ->restrictOnDelete();

            $table->decimal('guaranteed_amount', 18, 2);
            $table->string('status', 30)->default('pending')->index();

            $table->timestamp('responded_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique([
                'loan_application_id',
                'group_member_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
    }
};
