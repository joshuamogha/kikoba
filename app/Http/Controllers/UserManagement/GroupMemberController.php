<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\LinkGroupMemberRequest;
use App\Http\Requests\UserManagement\StoreGroupMemberRequest;
use App\Http\Requests\UserManagement\UpdateGroupMemberRequest;
use App\Http\Requests\UserManagement\UpdateGroupMemberStatusRequest;
use App\Http\Resources\UserManagement\GroupMemberResource;
use App\Models\UserManagement\Group;
use App\Models\UserManagement\GroupMember;
use App\Models\UserManagement\GroupRole;
use App\Models\UserManagement\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GroupMemberController extends Controller
{
    /**
     * Show the members page, or the paginated member list (search and status filter) as JSON.
     */
    public function index(Request $request, Group $group): View|JsonResponse
    {
        Gate::authorize('view', $group);

        $canManage = Gate::allows('manageMembers', $group);

        if (! $request->expectsJson()) {
            return view('backend.pages.groups.members', [
                'group' => $group,
                'canManage' => $canManage,
                'membershipTypes' => $group->membershipTypes()->where('is_active', true)->orderBy('display_order')->get(['id', 'name']),
                'assignableRoles' => $this->assignableRoles($group),
            ]);
        }

        $request->attributes->set('can_manage', $canManage);

        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $members = $group->groupMembers()
            ->with([
                'user:id,email',
                'membershipType:id,name',
                'groupMemberRoles' => fn ($query) => $query->active()->with('groupRole:id,name'),
            ])
            ->when(in_array($status, ['active', 'suspended', 'exited'], true), fn ($query) => $query->where('membership_status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('member_number', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$search}%"))))
            ->orderBy('member_number')
            ->paginate(15)
            ->withQueryString();

        return GroupMemberResource::collection($members)->response();
    }

    /**
     * Preview what adding a phone number or email would do. Never lists users: only an exact match is revealed.
     */
    public function lookup(Request $request, Group $group): JsonResponse
    {
        Gate::authorize('manageMembers', $group);

        $request->validate(['q' => ['required', 'string', 'max:255']]);

        $login = $request->string('q')->toString();
        $user = $this->findUser($login);
        $phone = $user?->phone_number ?? $this->phoneFrom($login);

        if ($phone === null) {
            return response()->json(['data' => null]);
        }

        $existing = $this->existingMembership($group, $phone, $user);

        return response()->json(['data' => [
            'registered' => $user !== null,
            'name' => $user?->name,
            'phone' => $user !== null
                ? substr($phone, 0, 4).str_repeat('•', max(0, strlen($phone) - 7)).substr($phone, -3)
                : $phone,
            'already_member' => $existing !== null && $existing->membership_status !== 'exited',
        ]]);
    }

    /**
     * Add a person by phone number and name. A phone number that belongs to a registered user links to their
     * account; any other number becomes a member without an account. A phone number is unique within a group.
     */
    public function store(StoreGroupMemberRequest $request, Group $group): JsonResponse
    {
        $login = $request->string('login')->toString();
        $user = $this->findUser($login);
        $phone = $user?->phone_number ?? $this->phoneFrom($login);

        if ($phone === null) {
            return $this->failure('login', 'Enter a valid phone number, or the email of a registered user.');
        }

        if ($user !== null && ! $user->canSignIn()) {
            return $this->failure('login', 'That user account is inactive.');
        }

        $member = DB::transaction(function () use ($request, $group, $user, $phone) {
            Group::query()->whereKey($group->id)->lockForUpdate()->first();

            $existing = $this->existingMembership($group, $phone, $user);

            if ($existing !== null && $existing->membership_status !== 'exited') {
                return $this->failure('login', 'That phone number already belongs to a member of this group.');
            }

            $current = $group->groupMembers()->where('membership_status', '!=', 'exited')->count();

            if ($group->maximum_members !== null && $current >= $group->maximum_members) {
                return $this->failure('login', 'This group has reached its maximum number of members.');
            }

            $attributes = [
                'name' => $request->string('name')->toString(),
                'phone_number' => $phone,
                'membership_type_id' => $request->integer('membership_type_id'),
                'joined_at' => $request->input('joined_at', now()->toDateString()),
                'membership_status' => 'active',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'left_at' => null,
                'exit_reason' => null,
            ];

            if ($existing !== null) {
                $existing->update($attributes + ['user_id' => $existing->user_id ?? $user?->id]);
                $member = $existing;
            } else {
                $member = $group->groupMembers()->create([
                    ...$attributes,
                    'user_id' => $user?->id,
                    'member_number' => $group->nextMemberNumber(),
                ]);
            }

            $this->giveMemberRole($member, $request->user());

            return $member;
        });

        if ($member instanceof JsonResponse) {
            return $member;
        }

        return $this->memberResponse($request, $member, $member->isLinked() ? 'Member added and linked to their account.' : 'Member added (no account yet).', 201);
    }

    /**
     * Change a member's name, membership type, and (while they have no account) phone number.
     */
    public function update(UpdateGroupMemberRequest $request, Group $group, GroupMember $groupMember): JsonResponse
    {
        $groupMember->update([
            'name' => $request->string('name')->toString(),
            'membership_type_id' => $request->integer('membership_type_id'),
            ...(! $groupMember->isLinked() && $request->filled('phone_number') ? ['phone_number' => $request->string('phone_number')->toString()] : []),
        ]);

        return $this->memberResponse($request, $groupMember, 'Member updated successfully.');
    }

    /**
     * Connect a member who has no account to a registered user, confirmed by a leader.
     */
    public function link(LinkGroupMemberRequest $request, Group $group, GroupMember $groupMember): JsonResponse
    {
        if ($groupMember->isLinked()) {
            return $this->failure('login', 'This member already has an account linked.');
        }

        $user = $this->findUser($request->string('login')->toString());

        if ($user === null || ! $user->canSignIn()) {
            return $this->failure('login', 'No active registered user was found with that phone number or email.');
        }

        if ($group->groupMembers()->where('user_id', $user->id)->exists()) {
            return $this->failure('login', 'That user is already a member of this group.');
        }

        $groupMember->update(['user_id' => $user->id]);

        return $this->memberResponse($request, $groupMember, 'Account linked. They can now sign in and see this group.');
    }

    /**
     * Suspend, reinstate or exit a member. Exiting ends all of their roles.
     */
    public function updateStatus(UpdateGroupMemberStatusRequest $request, Group $group, GroupMember $groupMember): JsonResponse
    {
        $status = $request->string('status')->toString();

        if ($status !== 'active' && $this->isOnlyChairperson($group, $groupMember)) {
            return $this->failure('status', 'This member is the only Chairperson. Assign another Chairperson first.');
        }

        DB::transaction(function () use ($request, $groupMember, $status): void {
            $groupMember->update([
                'membership_status' => $status,
                'left_at' => $status === 'exited' ? now()->toDateString() : null,
                'exit_reason' => $status === 'exited' ? $request->input('reason') : null,
            ]);

            if ($status === 'exited') {
                $groupMember->groupMemberRoles()->active()->get()->each->end();
            }

            if ($status === 'active') {
                $this->giveMemberRole($groupMember, $request->user());
            }
        });

        return $this->memberResponse($request, $groupMember, 'Member status updated.');
    }

    /**
     * Delete a member who has no history. Anyone with contributions, loans, shares or fines must be deactivated or exited instead,
     * so the group's books stay complete.
     */
    public function destroy(Request $request, Group $group, GroupMember $groupMember): JsonResponse
    {
        Gate::authorize('manageMembers', $group);

        if ($groupMember->user_id !== null && $groupMember->user_id === $request->user()->id) {
            return $this->failure('member', 'You cannot delete your own membership.');
        }

        if ($this->isOnlyChairperson($group, $groupMember)) {
            return $this->failure('member', 'This member is the only Chairperson. Assign another Chairperson first.');
        }

        if ($this->hasRecords($groupMember)) {
            return $this->failure('member', 'This member has contributions, loans, shares, fines or other records, so they cannot be deleted. Deactivate them instead.');
        }

        DB::transaction(function () use ($groupMember): void {
            $groupMember->groupMemberRoles()->delete();
            $groupMember->delete();
        });

        return response()->json(['success' => true, 'message' => 'Member deleted.']);
    }

    /**
     * Whether anything in the group's books refers to this member.
     */
    private function hasRecords(GroupMember $member): bool
    {
        return $member->contributions()->exists()
            || $member->loanApplications()->exists()
            || $member->loanGuarantors()->exists()
            || $member->loans()->exists()
            || $member->loanRepayments()->exists()
            || $member->memberFines()->exists()
            || $member->paymentTransactions()->exists()
            || $member->memberShareOuts()->exists()
            || $member->memberShareTransactions()->exists();
    }

    /**
     * Roles a leader can assign: system roles except Member, plus the group's active custom roles.
     *
     * @return array<int, array{id: int, name: string, custom: bool}>
     */
    private function assignableRoles(Group $group): array
    {
        return GroupRole::query()
            ->where('status', 'active')
            ->where('name', '!=', GroupRole::MEMBER)
            ->where(fn ($query) => $query->whereNull('group_id')->orWhere('group_id', $group->id))
            ->orderByRaw('group_id is not null')
            ->orderBy('id')
            ->get()
            ->map(fn (GroupRole $role) => ['id' => $role->id, 'name' => $role->name, 'custom' => $role->group_id !== null])
            ->all();
    }

    /**
     * A normalised phone number from free text, or null when the text is not a phone number.
     */
    private function phoneFrom(string $login): ?string
    {
        if (filter_var(trim($login), FILTER_VALIDATE_EMAIL) !== false) {
            return null;
        }

        $phone = User::normalizePhone($login);

        return preg_match('/^\+?[0-9]{9,15}$/', $phone) === 1 ? $phone : null;
    }

    /**
     * The group's membership row for this phone number (or the same registered user), whatever its status.
     */
    private function existingMembership(Group $group, string $phone, ?User $user): ?GroupMember
    {
        return $group->groupMembers()
            ->where(fn ($query) => $query
                ->where('phone_number', $phone)
                ->when($user !== null, fn ($inner) => $inner->orWhere('user_id', $user->id)))
            ->first();
    }

    private function findUser(string $login): ?User
    {
        $login = trim($login);

        return filter_var($login, FILTER_VALIDATE_EMAIL) !== false
            ? User::query()->where('email', strtolower($login))->first()
            : User::query()->where('phone_number', User::normalizePhone($login))->first();
    }

    private function isOnlyChairperson(Group $group, GroupMember $member): bool
    {
        $chairs = $group->activeRoleAssignments(GroupRole::CHAIRPERSON)->get();

        return $chairs->count() === 1 && $chairs->first()->group_member_id === $member->id;
    }

    /**
     * Every active member holds the system "Member" role.
     */
    private function giveMemberRole(GroupMember $member, User $assigner): void
    {
        $member->giveMemberRole($assigner);
    }

    private function memberResponse(Request $request, GroupMember $member, string $message, int $status = 200): JsonResponse
    {
        $request->attributes->set('can_manage', true);

        $member->load(['user:id,email', 'membershipType', 'groupMemberRoles' => fn ($query) => $query->active()->with('groupRole:id,name')]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new GroupMemberResource($member),
        ], $status);
    }

    private function failure(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
