<?php

namespace App\Http\Controllers\UserManagement;

use App\Actions\UserManagement\ImportGroupMembers;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentManagement\Concerns\ResolvesPaymentGroup;
use App\Http\Requests\UserManagement\ImportGroupMembersRequest;
use App\Http\Resources\UserManagement\GroupMemberResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroupMemberListController extends Controller
{
    use ResolvesPaymentGroup;

    /**
     * Show the members page, or the chosen group's members as JSON.
     */
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('backend.pages.user-management.members', ['groups' => $this->groupOptions($request->user())]);
        }

        $group = $this->groupFor($request);
        $canManage = Gate::allows('manageMembers', $group);
        $request->attributes->set('can_manage', $canManage);

        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $purchases = fn ($query) => $query->where('transaction_type', 'purchase')->where('status', 'approved');

        $members = $group->groupMembers()
            ->withSum(['memberShareTransactions as shares_count' => $purchases], 'number_of_shares')
            ->withSum(['memberShareTransactions as shares_amount' => $purchases], 'total_amount')
            ->with(['user:id,email', 'membershipType:id,name'])
            ->when(in_array($status, ['active', 'suspended', 'exited'], true), fn ($query) => $query->where('membership_status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('member_number', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%")))
            ->orderBy('member_number')
            ->paginate(15)
            ->withQueryString();

        return GroupMemberResource::collection($members)->additional([
            'can_manage' => $canManage,
            'options' => $canManage ? [
                'membership_types' => $this->options($group->membershipTypes()->where('is_active', true)->orderBy('display_order')->orderBy('id')),
            ] : null,
        ])->response();
    }

    /**
     * The CSV template for importing members.
     */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ImportGroupMembers::COLUMNS, escape: '\\');
            fputcsv($out, ['Asha Juma', '0712345678'], escape: '\\');
            fputcsv($out, ['John Mushi', '+255754123456'], escape: '\\');
            fclose($out);
        }, 'members-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Import members from an uploaded CSV file.
     */
    public function import(ImportGroupMembersRequest $request, ImportGroupMembers $import): JsonResponse
    {
        try {
            $result = $import->handle($request->group(), $request->user(), $request->file('file'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['file' => [$exception->getMessage()]]], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['imported'].' '.str('member')->plural($result['imported']).' imported'.($result['skipped'] === [] ? '.' : ', '.count($result['skipped']).' skipped.'),
            'data' => $result,
        ]);
    }
}
