<?php

namespace App\Models\PaymentManagement;

use App\Models\SettingManagement\MemberFine;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['member_fine_id', 'payment_number', 'payment_date', 'amount', 'payment_method_id', 'external_reference', 'status', 'received_by'])]
class FinePayment extends Model
{
    /**
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
     * @return BelongsTo<MemberFine, $this>
     */
    public function memberFine(): BelongsTo
    {
        return $this->belongsTo(MemberFine::class);
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
     * @return MorphMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'reference');
    }
}
