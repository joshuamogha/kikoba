<?php

namespace App\Http\Controllers\LoanManagement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\LoanManagement\RespondLoanGuarantorRequest;
use App\Http\Requests\LoanManagement\StoreLoanGuarantorRequest;
use App\Http\Resources\LoanManagement\LoanGuarantorResource;
use App\Models\LoanManagement\LoanGuarantor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoanGuarantorController extends Controller
{
    use ResolvesPaymentGroup;

    private const RELATIONS = ['groupMember', 'loanApplication.groupMember'];

    /**
     * Show the page, or the group's guarantees as JSON: everyone's for leaders and the Treasurer, otherwise those
     * the viewer gives or receives.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.loan-management.loan-guarantors', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('manageLoans', $group);
        $userId = $request->user()->id;
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        $rows = LoanGuarantor::query()
            ->whereHas('loanApplication', fn ($application) => $application->where('group_id', $group->id))
            ->with(self::RELATIONS)
            ->when(! $canManage, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('groupMember', fn ($member) => $member->where('user_id', $userId))
                ->orWhereHas('loanApplication.groupMember', fn ($member) => $member->where('user_id', $userId))))
            ->when(in_array($status, ['pending', 'accepted', 'declined'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('groupMember', fn ($member) => $member->where('name', 'like', "%{$search}%"))
                ->orWhereHas('loanApplication', fn ($application) => $application->where('application_number', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $applications = $group->loanApplications()->where('status', 'submitted')
            ->when(! $canManage, fn ($query) => $query->whereHas('groupMember', fn ($member) => $member->where('user_id', $userId)))
            ->orderByDesc('id')->get(['id', 'application_number']);

        return LoanGuarantorResource::collection($rows)->additional([
            'can_manage' => $canManage,
            'options' => [
                'applications' => $applications->map(fn ($application) => ['value' => $application->id, 'label' => $application->application_number])->all(),
                'members' => $this->memberOptions($group),
            ],
        ])->response();
    }

    public function store(StoreLoanGuarantorRequest $request): JsonResponse
    {
        $guarantor = LoanGuarantor::query()->create([...$request->validated(), 'status' => 'pending']);

        return $this->saved($guarantor, 'Guarantor added.', 201);
    }

    public function respond(RespondLoanGuarantorRequest $request, LoanGuarantor $loanGuarantor): JsonResponse
    {
        if ($loanGuarantor->status !== 'pending' || $loanGuarantor->loanApplication->status !== 'submitted') {
            return $this->failure('This guarantee can no longer be answered.');
        }

        $loanGuarantor->update([
            'status' => $request->validated('decision'),
            'remarks' => $request->validated('remarks'),
            'responded_at' => now(),
        ]);

        return $this->saved($loanGuarantor, $request->validated('decision') === 'accepted' ? 'Guarantee accepted.' : 'Guarantee declined.');
    }

    private function saved(LoanGuarantor $guarantor, string $message, int $status = 200): JsonResponse
    {
        $guarantor->load(self::RELATIONS);

        return response()->json(['success' => true, 'message' => $message, 'data' => (new LoanGuarantorResource($guarantor))->resolve()], $status);
    }
}
