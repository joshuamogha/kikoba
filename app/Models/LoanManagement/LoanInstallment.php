<?php

namespace App\Models\LoanManagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_id', 'installment_number', 'due_date', 'principal_due', 'interest_due', 'penalty_due', 'total_due', 'principal_paid', 'interest_paid', 'penalty_paid', 'total_paid', 'status', 'paid_at'])]
class LoanInstallment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal_due' => 'decimal:2',
            'interest_due' => 'decimal:2',
            'penalty_due' => 'decimal:2',
            'total_due' => 'decimal:2',
            'principal_paid' => 'decimal:2',
            'interest_paid' => 'decimal:2',
            'penalty_paid' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
