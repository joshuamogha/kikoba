<?php

namespace App\Http\Controllers\ShareManagement;

use App\Actions\ShareManagement\ImportMemberShares;
use App\Actions\ShareManagement\RecordSharePurchase;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShareManagement\ImportMemberSharesRequest;
use App\Http\Requests\ShareManagement\StoreGroupShareRequest;
use App\Http\Resources\ShareManagement\MemberShareTransactionResource;
use App\Models\UserManagement\Group;
use App\Support\BillingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroupShareController extends Controller
{
    /**
     * Show the shares page, or the group's share purchases (with member options and the default price) as JSON.
     */
    public function index(Request $request, Group $group): View|JsonResponse
    {
        Gate::authorize('view', $group);

        $canManage = Gate::allows('manageShareOuts', $group);

        if (! $request->expectsJson()) {
            return view('backend.pages.groups.shares', ['group' => $group, 'canManage' => $canManage]);
        }

        $user = $request->user();
        $memberId = $canManage ? $request->integer('group_member_id') : 0;
        $month = BillingPeriod::monthFilter($request->string('month')->toString());

        $query = $group->memberShareTransactions()
            ->with(['group:id,name', 'financialCycle:id,name', 'groupMember'])
            ->when(! $canManage, fn ($rows) => $rows->whereHas('groupMember', fn ($member) => $member->where('user_id', $user->id)))
            ->when($memberId > 0, fn ($rows) => $rows->where('group_member_id', $memberId))
            ->when($month !== null, fn ($rows) => $rows->whereRaw('coalesce(period_start, transaction_date) between ? and ?', [$month[0], $month[1]]))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        $totals = $query->clone()->toBase()->reorder()->selectRaw('coalesce(sum(number_of_shares), 0) as shares, coalesce(sum(total_amount), 0) as amount')->first();

        return MemberShareTransactionResource::collection($query->paginate(15)->withQueryString())
            ->additional([
                'filters' => ['members' => $canManage ? $this->memberFilterOptions($group) : []],
                'totals' => ['shares' => (int) $totals->shares, 'amount' => (float) $totals->amount],
                'meta_options' => $canManage ? [
                    'price_per_share' => (float) $group->ensureGroupSetting()->share_price,
                    'minimum_shares' => $group->ensureGroupSetting()->minimum_shares,
                    'maximum_shares' => $group->ensureGroupSetting()->maximum_shares,
                    'members' => $group->groupMembers()->where('membership_status', 'active')->orderBy('name')->get(['id', 'name', 'member_number'])
                        ->map(fn ($member) => ['value' => $member->id, 'label' => "{$member->name} ({$member->member_number})"])->all(),
                ] : null,
            ])
            ->response();
    }

    /**
     * Record shares bought by a member. When the member's monthly maximum is reached, the rest moves to the next month(s).
     */
    public function store(StoreGroupShareRequest $request, Group $group, RecordSharePurchase $recordPurchase): JsonResponse
    {
        $data = $request->validated();
        $date = Carbon::parse($data['transaction_date']);

        try {
            $records = $recordPurchase->handle(
                $group,
                $group->groupMembers()->findOrFail($data['group_member_id']),
                $request->user(),
                (int) $data['number_of_shares'],
                $date,
                BillingPeriod::start($data['period'] ?? null, $date),
                $data['reference'] ?? null,
                (float) $group->ensureGroupSetting()->share_price,
                $group->ensureGroupSetting()->maximum_shares,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['number_of_shares' => [$exception->getMessage()]]], 422);
        }

        foreach ($records as $record) {
            $record->load(['group:id,name', 'financialCycle:id,name', 'groupMember']);
        }

        return response()->json([
            'success' => true,
            'message' => count($records) > 1
                ? $data['number_of_shares'].' shares recorded: '.RecordSharePurchase::describe($records).'.'
                : 'Shares recorded successfully.',
            'data' => (new MemberShareTransactionResource($records[0]))->resolve(),
            'records' => collect($records)->map(fn ($record) => (new MemberShareTransactionResource($record))->resolve())->all(),
        ], 201);
    }

    /**
     * The CSV template for importing share purchases, identified by phone number.
     */
    public function template(Group $group): StreamedResponse
    {
        Gate::authorize('manageShareOuts', $group);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [...ImportMemberShares::REQUIRED, 'receipt'], escape: '\\');
            fputcsv($out, ['0712345678', '20000', '2026-02-05', 'RCT-0001'], escape: '\\');
            fputcsv($out, ['+255754123456', '10000', '2026-03-10', 'RCT-0002'], escape: '\\');
            fclose($out);
        }, 'member-shares-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Import share purchases from an uploaded CSV file.
     */
    public function import(ImportMemberSharesRequest $request, Group $group, ImportMemberShares $import): JsonResponse
    {
        try {
            $result = $import->handle($group, $request->user(), $request->file('file'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['file' => [$exception->getMessage()]]], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['imported'].' share '.str('record')->plural($result['imported']).' imported'
                .($result['carried'] > 0 ? ', '.$result['carried'].' carried over to the next month' : '')
                .($result['skipped'] === [] ? '.' : ', '.count($result['skipped']).' skipped.'),
            'data' => $result,
        ]);
    }

    /**
     * Every member of the group (also those who left), for the member filter.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function memberFilterOptions(Group $group): array
    {
        return $group->groupMembers()->orderBy('name')->get(['id', 'name', 'member_number'])
            ->map(fn ($member) => ['value' => $member->id, 'label' => "{$member->name} ({$member->member_number})"])->all();
    }
}
