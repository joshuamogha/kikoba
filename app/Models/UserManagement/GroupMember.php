<?php

namespace App\Models\UserManagement;

use App\Models\LoanManagement\Loan;
use App\Models\LoanManagement\LoanApplication;
use App\Models\LoanManagement\LoanGuarantor;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\PaymentTransaction;
use App\Models\SettingManagement\MemberFine;
use App\Models\SettingManagement\MembershipType;
use App\Models\ShareManagement\MemberShareOut;
use App\Models\ShareManagement\MemberShareTransaction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'user_id', 'name', 'phone_number', 'membership_type_id', 'member_number', 'joined_at', 'membership_status', 'approved_at', 'approved_by', 'left_at', 'exit_reason'])]
class GroupMember extends Model
{
    /**
     * Whether the member is connected to a registered account.
     */
    public function isLinked(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Give the member the system "Member" role that every active member holds, unless they already have it.
     */
    public function giveMemberRole(User $assigner): void
    {
        $roleId = GroupRole::query()->whereNull('group_id')->where('name', GroupRole::MEMBER)->value('id');

        if ($roleId === null || $this->groupMemberRoles()->active()->where('group_role_id', $roleId)->exists()) {
            return;
        }

        GroupMemberRole::query()->create([
            'group_member_id' => $this->id,
            'group_role_id' => $roleId,
            'starts_at' => now()->toDateString(),
            'status' => 'active',
            'assigned_by' => $assigner->id,
        ]);
    }

    public function initials(): string
    {
        return User::makeInitials($this->name);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MembershipType, $this>
     */
    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<GroupMemberRole, $this>
     */
    public function groupMemberRoles(): HasMany
    {
        return $this->hasMany(GroupMemberRole::class);
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * @return HasMany<LoanApplication, $this>
     */
    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    /**
     * @return HasMany<LoanGuarantor, $this>
     */
    public function loanGuarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @return HasMany<LoanRepayment, $this>
     */
    public function loanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    /**
     * @return HasMany<MemberFine, $this>
     */
    public function memberFines(): HasMany
    {
        return $this->hasMany(MemberFine::class);
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * @return HasMany<MemberShareOut, $this>
     */
    public function memberShareOuts(): HasMany
    {
        return $this->hasMany(MemberShareOut::class);
    }

    /**
     * @return HasMany<MemberShareTransaction, $this>
     */
    public function memberShareTransactions(): HasMany
    {
        return $this->hasMany(MemberShareTransaction::class);
    }

    /**
     * @return BelongsToMany<GroupRole, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(GroupRole::class, 'group_member_roles')
            ->withPivot(['starts_at', 'ends_at', 'status'])
            ->withTimestamps();
    }
}
