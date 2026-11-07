<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\PaymentManagement\StoreFinancialCycleRequest;
use App\Http\Requests\PaymentManagement\UpdateFinancialCycleRequest;
use App\Http\Resources\PaymentManagement\FinancialCycleResource;
use App\Models\PaymentManagement\FinancialCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialCycleController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * Show the page, or the group's cycles (with the frequency options) as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.payment-management.financial-cycles', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $cycles = $group->financialCycles()
            ->with('contributionFrequency:id,name')
            ->when(in_array($status, ['draft', 'open', 'closed'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return FinancialCycleResource::collection($cycles)->additional([
            'can_manage' => $this->canManagePayments($group),
            'options' => ['frequencies' => $this->options($group->contributionFrequencies()->where('is_active', true)->orderBy('display_order')->orderBy('id'))],
        ])->response();
    }

    public function store(StoreFinancialCycleRequest $request): JsonResponse
    {
        $group = $request->group();

        $cycle = $group->financialCycles()->create([
            ...$request->validated(),
            'cycle_number' => $group->financialCycles()->max('cycle_number') + 1,
            'status' => 'draft',
        ]);

        return $this->saved($cycle, 'Cycle created successfully.', 201);
    }

    public function update(UpdateFinancialCycleRequest $request, FinancialCycle $financialCycle): JsonResponse
    {
        abort_unless($financialCycle->group_id === $request->group()->id, 404);

        if ($financialCycle->status === 'closed') {
            return $this->failure('A closed cycle cannot be edited.');
        }

        $financialCycle->update($request->validated());

        return $this->saved($financialCycle, 'Cycle updated successfully.');
    }

    public function open(Request $request, FinancialCycle $financialCycle): JsonResponse
    {
        Gate::authorize('managePayments', $financialCycle->group);

        if (! in_array($financialCycle->status, ['draft', 'closed'], true)) {
            return $this->failure('Only a draft or closed cycle can be opened.');
        }

        $reopened = $financialCycle->status === 'closed';

        $financialCycle->update([
            'status' => 'open',
            'opened_by' => $request->user()->id,
            ...($reopened ? ['closed_by' => null, 'closed_at' => null] : []),
        ]);

        return $this->saved($financialCycle, $reopened ? 'Cycle reopened.' : 'Cycle opened.');
    }

    /**
     * Delete a cycle that nothing refers to. A cycle with records is closed instead.
     */
    public function destroy(Request $request, FinancialCycle $financialCycle): JsonResponse
    {
        Gate::authorize('managePayments', $financialCycle->group);

        $records = $financialCycle->contributions()->count()
            + $financialCycle->loanApplications()->count()
            + $financialCycle->loans()->count()
            + $financialCycle->memberFines()->count()
            + $financialCycle->groupIncomes()->count()
            + $financialCycle->groupExpenses()->count()
            + $financialCycle->memberShareTransactions()->count()
            + $financialCycle->shareOut()->count();

        if ($records > 0) {
            return $this->failure("This cycle has {$records} record(s) (contributions, shares, loans, ...), so it cannot be deleted. Close it instead.");
        }

        $financialCycle->delete();

        return response()->json(['success' => true, 'message' => 'Cycle deleted.']);
    }

    public function close(Request $request, FinancialCycle $financialCycle): JsonResponse
    {
        Gate::authorize('managePayments', $financialCycle->group);

        if ($financialCycle->status !== 'open') {
            return $this->failure('Only an open cycle can be closed.');
        }

        $financialCycle->update(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now()]);

        return $this->saved($financialCycle, 'Cycle closed.');
    }

    private function saved(FinancialCycle $cycle, string $message, int $status = 200): JsonResponse
    {
        $cycle->load('contributionFrequency:id,name');

        return response()->json(['success' => true, 'message' => $message, 'data' => (new FinancialCycleResource($cycle))->resolve()], $status);
    }
}
