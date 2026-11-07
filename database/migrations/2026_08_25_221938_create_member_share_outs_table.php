<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_share_outs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('share_out_id')
                ->constrained('share_outs')
                ->cascadeOnDelete();

            $table->foreignId('group_member_id')
                ->constrained('group_members')
                ->restrictOnDelete();

            $table->decimal('total_savings', 18, 2)->default(0);
            $table->unsignedInteger('number_of_shares')->default(0);
            $table->decimal('share_value', 18, 2)->default(0);

            $table->decimal('profit_amount', 18, 2)->default(0);
            $table->decimal('other_entitlements', 18, 2)->default(0);

            $table->decimal('outstanding_loan', 18, 2)->default(0);
            $table->decimal('outstanding_fines', 18, 2)->default(0);
            $table->decimal('other_deductions', 18, 2)->default(0);

            $table->decimal('net_payable', 18, 2);
            $table->decimal('amount_paid', 18, 2)->default(0);

            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->unique([
                'share_out_id',
                'group_member_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_share_outs');
    }
};
