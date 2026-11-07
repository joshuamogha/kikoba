<?php

namespace App\Models\LoanManagement;

use App\Models\PaymentManagement\PaymentMethod;
use App\Models\PaymentManagement\PaymentTransaction;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['loan_id', 'group_member_id', 'repayment_number', 'payment_date', 'amount', 'principal_amount', 'interest_amount', 'penalty_amount', 'payment_method_id', 'external_reference', 'status', 'received_by', 'approved_by', 'approved_at'])]
class LoanRepayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'payment_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<GroupMember, $this>
     */
    public function groupMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return MorphMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'reference');
    }
}
