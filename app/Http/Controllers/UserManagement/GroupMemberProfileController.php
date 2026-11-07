<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanManagement\LoanGuarantorResource;
use App\Http\Resources\LoanManagement\LoanRepaymentResource;
use App\Http\Resources\LoanManagement\LoanResource;
use App\Http\Resources\PaymentManagement\ContributionResource;
use App\Http\Resources\PaymentManagement\PaymentTransactionResource;
use App\Http\Resources\SettingManagement\MemberFineResource;
use App\Http\Resources\ShareManagement\MemberShareTransactionResource;
use App\Models\UserManagement\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GroupMemberProfileController extends Controller
{
    /**
     * The record lists shown as tabs on a member's profile.
     *
     * @var array<int, string>
     */
    public const TYPES = ['contributions', 'shares', 'loans', 'repayments', 'fines', 'transactions', 'guarantees'];

    /**
     * Show the member's profile page, or the member's details and summary figures as JSON.
     */
    public function show(Request $request, GroupMember $groupMember): View|JsonResponse
    {
        $this->authorizeMember($request, $groupMember);

        if (! $request->expectsJson()) {
            return view('backend.pages.user-management.member', ['member' => $groupMember->load('group:id,name')]);
        }

        $groupMember->load(['group:id,name,currency', 'membershipType:id,name', 'user:id,email', 'groupMemberRoles' => fn ($query) => $query->active()->with('groupRole:id,name')]);
        $canManage = Gate::allows('manageMembers', $groupMember->group);

        return response()->json(['data' => [
            'id' => $groupMember->id,
            'member_number' => $groupMember->member_number,
            'name' => $groupMember->name,
            'initials' => $groupMember->initials(),
            'phone' => $groupMember->phone_number,
            'email' => $canManage && $groupMember->isLinked() ? $groupMember->user->email : null,
            'has_account' => $groupMember->isLinked(),
            'membership_type' => $groupMember->membershipType->name,
            'membership_status' => $groupMember->membership_status,
            'joined_at' => $groupMember->joined_at,
            'left_at' => $groupMember->left_at,
            'exit_reason' => $groupMember->exit_reason,
            'group' => ['id' => $groupMember->group->id, 'name' => $groupMember->group->name, 'currency' => $groupMember->group->currency],
            'roles' => $groupMember->groupMemberRoles->map(fn ($assignment) => $assignment->groupRole->name)->values(),
            'summary' => $this->summary($groupMember),
        ]]);
    }

    /**
     * One of the member's record lists, paginated.
     */
    public function records(Request $request, GroupMember $groupMember, string $type): JsonResponse
    {
        $this->authorizeMember($request, $groupMember);

        [$query, $resource] = match ($type) {
            'contributions' => [$groupMember->contributions()->with(['groupMember', 'paymentMethod', 'contributionItems.groupFund'])->orderByDesc('contribution_date')->orderByDesc('id'), ContributionResource::class],
            'shares' => [$groupMember->memberShareTransactions()->with(['group:id,name', 'financialCycle:id,name', 'groupMember'])->orderByDesc('transaction_date')->orderByDesc('id'), MemberShareTransactionResource::class],
            'loans' => [$groupMember->loans()->with(['groupMember', 'loanProduct'])->orderByDesc('disbursement_date')->orderByDesc('id'), LoanResource::class],
            'repayments' => [$groupMember->loanRepayments()->with(['loan', 'groupMember', 'paymentMethod'])->orderByDesc('payment_date')->orderByDesc('id'), LoanRepaymentResource::class],
            'fines' => [$groupMember->memberFines()->with(['groupMember', 'fineType'])->orderByDesc('fine_date')->orderByDesc('id'), MemberFineResource::class],
            'transactions' => [$groupMember->paymentTransactions()->with(['paymentMethod', 'groupMember'])->orderByDesc('id'), PaymentTransactionResource::class],
            default => [$groupMember->loanGuarantors()->with(['groupMember', 'loanApplication.groupMember'])->orderByDesc('id'), LoanGuarantorResource::class],
        };

        /** @var class-string<JsonResource> $resource */
        return $resource::collection($query->paginate(10)->withQueryString())->response();
    }

    /**
     * Leaders and the Treasurer see any member; everyone else sees only their own profile.
     */
    private function authorizeMember(Request $request, GroupMember $member): void
    {
        $group = $member->group;

        abort_unless(
            Gate::allows('manageMembers', $group) || ($member->user_id !== null && $member->user_id === $request->user()->id),
            403,
        );
    }

    /**
     * Headline figures for the profile.
     *
     * @return array<string, mixed>
     */
    private function summary(GroupMember $member): array
    {
        $purchases = $member->memberShareTransactions()->where('transaction_type', 'purchase');

        return [
            'contributions_total' => (float) $member->contributions()->where('status', 'approved')->sum('total_amount'),
            'shares_count' => (int) $purchases->clone()->sum('number_of_shares'),
            'shares_amount' => (float) $purchases->clone()->sum('total_amount'),
            'loans_count' => $member->loans()->count(),
            'loans_outstanding' => (float) $member->loans()->whereIn('status', ['active', 'overdue'])->sum('outstanding_balance'),
            'fines_balance' => (float) $member->memberFines()->whereIn('status', ['pending', 'partial'])->sum('balance'),
            'guarantees_count' => $member->loanGuarantors()->where('status', 'accepted')->count(),
        ];
    }
}
