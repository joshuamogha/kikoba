<?php

namespace App\Models\ShareManagement;

use App\Models\PaymentManagement\PaymentMethod;
use App\Models\PaymentManagement\PaymentTransaction;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['member_share_out_id', 'payment_number', 'payment_date', 'amount', 'payment_method_id', 'external_reference', 'status', 'approved_by'])]
class ShareOutPayment extends Model
{
    public const PAID = 'paid';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MemberShareOut, $this>
     */
    public function memberShareOut(): BelongsTo
    {
        return $this->belongsTo(MemberShareOut::class);
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
