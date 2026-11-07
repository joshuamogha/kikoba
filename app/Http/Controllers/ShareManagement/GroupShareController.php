<?php

namespace App\Http\Controllers\ShareManagement;

use App\Actions\PaymentManagement\ResolveFinancialCycle;
use App\Actions\ShareManagement\ImportMemberShares;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShareManagement\ImportMemberSharesRequest;
use App\Http\Requests\ShareManagement\StoreGroupShareRequest;
use App\Http\Resources\ShareManagement\MemberShareTransactionResource;
use App\Models\ShareManagement\MemberShareTransaction;
use App\Models\UserManagement\Group;
use App\Support\BillingPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $query = $group->memberShareTransactions()
            ->with(['group:id,name', 'financialCycle:id,name', 'groupMember'])
            ->when(! $canManage, fn ($rows) => $rows->whereHas('groupMember', fn ($member) => $member->where('user_id', $user->id)))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        $totals = $query->clone()->toBase()->reorder()->selectRaw('coalesce(sum(number_of_shares), 0) as shares, coalesce(sum(total_amount), 0) as amount')->first();

        return MemberShareTransactionResource::collection($query->paginate(15))
            ->additional([
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
     * Record shares bought by a member, in the open cycle covering the date.
     */
    public function store(StoreGroupShareRequest $request, Group $group, ResolveFinancialCycle $resolveCycle): JsonResponse
    {
        $data = $request->validated();
        $price = (float) $group->ensureGroupSetting()->share_price;

        $transaction = DB::transaction(function () use ($request, $group, $data, $price, $resolveCycle): MemberShareTransaction {
            $date = Carbon::parse($data['transaction_date']);
            $period = BillingPeriod::start($data['period'] ?? null, $date);

            $transaction = $group->memberShareTransactions()->create([
                'financial_cycle_id' => $resolveCycle->handle($group, $period, $request->user()->id)->id,
                'group_member_id' => $data['group_member_id'],
                'transaction_number' => 'PENDING-'.uniqid(),
                'transaction_type' => 'purchase',
                'number_of_shares' => $data['number_of_shares'],
                'price_per_share' => $price,
                'total_amount' => round($data['number_of_shares'] * $price, 2),
                'transaction_date' => $date->toDateString(),
                'period_start' => $period->toDateString(),
                'status' => 'approved',
                'reference' => $data['reference'] ?? null,
                'approved_by' => $request->user()->id,
            ]);

            $transaction->update(['transaction_number' => sprintf('SHR-%d-%06d', $date->year, $transaction->id)]);

            return $transaction;
        });

        $transaction->load(['group:id,name', 'financialCycle:id,name', 'groupMember']);

        return response()->json([
            'success' => true,
            'message' => 'Shares recorded successfully.',
            'data' => (new MemberShareTransactionResource($transaction))->resolve(),
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
            fputcsv($out, [...ImportMemberShares::REQUIRED, ...ImportMemberShares::OPTIONAL], escape: '\\');
            fputcsv($out, ['0712345678', '20000', '2026-02-05', 'January shares paid in February'], escape: '\\');
            fputcsv($out, ['+255754123456', '10000', '2026-03-10', ''], escape: '\\');
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
            'message' => $result['imported'].' share '.str('record')->plural($result['imported']).' imported'.($result['skipped'] === [] ? '.' : ', '.count($result['skipped']).' skipped.'),
            'data' => $result,
        ]);
    }
}
