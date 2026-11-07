<?php

namespace App\Models\LoanManagement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_product_id', 'minimum_amount', 'maximum_amount', 'duration_months'])]
class LoanProductRange extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<LoanProduct, $this>
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }
}
