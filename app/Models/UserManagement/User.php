<?php

namespace App\Models\UserManagement;

use App\Models\LoanManagement\LoanApplication;
use App\Models\LoanManagement\LoanProduct;
use App\Models\LoanManagement\LoanRepayment;
use App\Models\PaymentManagement\Contribution;
use App\Models\PaymentManagement\FinancialCycle;
use App\Models\PaymentManagement\FinePayment;
use App\Models\PaymentManagement\GroupExpense;
use App\Models\PaymentManagement\GroupFund;
use App\Models\PaymentManagement\GroupIncome;
use App\Models\PaymentManagement\PaymentTransaction;
use App\Models\SettingManagement\MemberFine;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\ShareManagement\ShareOut;
use App\Models\ShareManagement\ShareOutPayment;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserManagement\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'phone_number', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Strip spaces, dashes and brackets from a phone number so lookups are consistent.
     */
    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone) ?? '';
    }

    /**
     * Two-letter initials of the user's name, e.g. "Neema Mushi" => "NM", "Madonna" => "MA".
     */
    public function initials(): string
    {
        return self::makeInitials($this->name);
    }

    /**
     * Initials for any display name (used for members without an account too).
     */
    public static function makeInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = count($words) > 1
            ? mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1)
            : mb_substr($words[0] ?? '', 0, 2);

        return mb_strtoupper($initials);
    }

    /**
     * Whether the user is an active member of the group.
     */
    public function isMemberOf(Group $group): bool
    {
        return $this->groupMembers()
            ->where('group_id', $group->id)
            ->where('membership_status', 'active')
            ->exists();
    }

    /**
     * Whether the user currently holds Chairperson, Vice Chairperson or Secretary in the group.
     */
    public function leadsGroup(Group $group): bool
    {
        return $this->holdsGroupRole($group, GroupRole::LEADERSHIP);
    }

    /**
     * Whether the user currently holds one of the given system roles in the group.
     *
     * @param  array<int, string>  $roleNames
     */
    public function holdsGroupRole(Group $group, array $roleNames): bool
    {
        return GroupMemberRole::query()
            ->active()
            ->whereHas('groupRole', fn ($query) => $query->whereNull('group_id')->whereIn('name', $roleNames))
            ->whereHas('groupMember', fn ($query) => $query
                ->where('group_id', $group->id)
                ->where('user_id', $this->id)
                ->where('membership_status', 'active'))
            ->exists();
    }

    /**
     * Ids of the groups where the user is an active member.
     *
     * @return Collection<int, int>
     */
    public function memberGroupIds(): Collection
    {
        return $this->groupMembers()->where('membership_status', 'active')->pluck('group_id');
    }

    /**
     * Ids of the groups where the user currently holds a leadership role or Treasurer.
     *
     * @return Collection<int, int>
     */
    public function managedGroupIds(): Collection
    {
        return $this->groupMembers()
            ->where('membership_status', 'active')
            ->whereHas('groupMemberRoles', fn ($assignments) => $assignments
                ->active()
                ->whereHas('groupRole', fn ($role) => $role->whereNull('group_id')->whereIn('name', [...GroupRole::LEADERSHIP, GroupRole::TREASURER])))
            ->pluck('group_id');
    }

    /**
     * Determine whether the account may sign in.
     */
    public function canSignIn(): bool
    {
        return $this->is_active && $this->status === 'active';
    }

    /**
     * Send the email verification notification through the queue.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /**
     * Send the password reset notification through the queue.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function createdGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'created_by');
    }

    /**
     * @return HasMany<GroupMember, $this>
     */
    public function groupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * @return HasMany<GroupMember, $this>
     */
    public function approvedGroupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'approved_by');
    }

    /**
     * @return HasMany<GroupMemberRole, $this>
     */
    public function assignedGroupMemberRoles(): HasMany
    {
        return $this->hasMany(GroupMemberRole::class, 'assigned_by');
    }

    /**
     * @return HasMany<FinancialCycle, $this>
     */
    public function openedFinancialCycles(): HasMany
    {
        return $this->hasMany(FinancialCycle::class, 'opened_by');
    }

    /**
     * @return HasMany<FinancialCycle, $this>
     */
    public function closedFinancialCycles(): HasMany
    {
        return $this->hasMany(FinancialCycle::class, 'closed_by');
    }

    /**
     * @return HasMany<GroupFund, $this>
     */
    public function createdGroupFunds(): HasMany
    {
        return $this->hasMany(GroupFund::class, 'created_by');
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function receivedContributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'received_by');
    }

    /**
     * @return HasMany<Contribution, $this>
     */
    public function approvedContributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'approved_by');
    }

    /**
     * @return HasMany<LoanProduct, $this>
     */
    public function createdLoanProducts(): HasMany
    {
        return $this->hasMany(LoanProduct::class, 'created_by');
    }

    /**
     * @return HasMany<LoanApplication, $this>
     */
    public function reviewedLoanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class, 'reviewed_by');
    }

    /**
     * @return HasMany<LoanApplication, $this>
     */
    public function approvedLoanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class, 'approved_by');
    }

    /**
     * @return HasMany<LoanRepayment, $this>
     */
    public function receivedLoanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class, 'received_by');
    }

    /**
     * @return HasMany<LoanRepayment, $this>
     */
    public function approvedLoanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class, 'approved_by');
    }

    /**
     * @return HasMany<MemberFine, $this>
     */
    public function issuedMemberFines(): HasMany
    {
        return $this->hasMany(MemberFine::class, 'issued_by');
    }

    /**
     * @return HasMany<MemberFine, $this>
     */
    public function approvedMemberFines(): HasMany
    {
        return $this->hasMany(MemberFine::class, 'approved_by');
    }

    /**
     * @return HasMany<GroupIncome, $this>
     */
    public function receivedGroupIncomes(): HasMany
    {
        return $this->hasMany(GroupIncome::class, 'received_by');
    }

    /**
     * @return HasMany<GroupIncome, $this>
     */
    public function approvedGroupIncomes(): HasMany
    {
        return $this->hasMany(GroupIncome::class, 'approved_by');
    }

    /**
     * @return HasMany<GroupExpense, $this>
     */
    public function requestedGroupExpenses(): HasMany
    {
        return $this->hasMany(GroupExpense::class, 'requested_by');
    }

    /**
     * @return HasMany<GroupExpense, $this>
     */
    public function approvedGroupExpenses(): HasMany
    {
        return $this->hasMany(GroupExpense::class, 'approved_by');
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function createdPaymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'created_by');
    }

    /**
     * @return HasMany<ShareOut, $this>
     */
    public function calculatedShareOuts(): HasMany
    {
        return $this->hasMany(ShareOut::class, 'calculated_by');
    }

    /**
     * @return HasMany<ShareOut, $this>
     */
    public function approvedShareOuts(): HasMany
    {
        return $this->hasMany(ShareOut::class, 'approved_by');
    }

    /**
     * @return HasMany<ShareOutPayment, $this>
     */
    public function approvedShareOutPayments(): HasMany
    {
        return $this->hasMany(ShareOutPayment::class, 'approved_by');
    }

    /**
     * @return HasMany<MemberShareTransaction, $this>
     */
    public function approvedMemberShareTransactions(): HasMany
    {
        return $this->hasMany(MemberShareTransaction::class, 'approved_by');
    }

    /**
     * @return HasMany<FinePayment, $this>
     */
    public function receivedFinePayments(): HasMany
    {
        return $this->hasMany(FinePayment::class, 'received_by');
    }
}
