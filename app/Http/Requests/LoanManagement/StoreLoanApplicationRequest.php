<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use App\Models\LoanManagement\LoanProduct;
use App\Models\PaymentManagement\ContributionItem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanApplicationRequest extends GroupScopedRequest
{
    /**
     * Members may apply for themselves; leaders and the Treasurer may apply for anyone.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->group());
    }

    private function canManage(): bool
    {
        return $this->user()->can('manageLoans', $this->group());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $own = $this->group()->groupMembers()->where('user_id', $this->user()->id)->where('membership_status', 'active')->value('id');

        return [
            'group_member_id' => [
                'required', 'integer',
                $this->inGroup('group_members', 'membership_status', 'active'),
                ...($this->canManage() ? [] : [Rule::in([$own])]),
            ],
            'loan_product_id' => ['required', 'integer', $this->inGroup('loan_products', 'status', 'active')],
            'requested_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'purpose' => ['required', 'string', 'max:1000'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }

    /**
     * Amount and duration must fit the product, and the member must be eligible.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $product = LoanProduct::query()->findOrFail($this->integer('loan_product_id'));
            $amount = (float) $this->input('requested_amount');
            $months = $this->integer('duration_months');
            $memberId = $this->integer('group_member_id');

            if ($amount < (float) $product->minimum_amount || ($product->maximum_amount !== null && $amount > (float) $product->maximum_amount)) {
                $validator->errors()->add('requested_amount', 'The amount is outside this product\'s limits.');
            }

            if ($product->hasRanges()) {
                if ($product->termFor($amount) === null) {
                    $validator->errors()->add('requested_amount', 'No loan range covers this amount.');
                }
            } elseif (! $this->filled('duration_months')) {
                $validator->errors()->add('duration_months', 'The duration is required.');
            } elseif ($months < $product->minimum_duration_months || $months > $product->maximum_duration_months) {
                $validator->errors()->add('duration_months', "The duration must be between {$product->minimum_duration_months} and {$product->maximum_duration_months} months.");
            }

            $multiplier = (float) $product->maximum_savings_multiplier;
            $savings = (float) ContributionItem::query()
                ->whereHas('contribution', fn ($contribution) => $contribution->where('group_member_id', $memberId)->where('status', 'approved'))
                ->sum('amount');

            if ($multiplier > 0 && $amount > $savings * $multiplier) {
                $validator->errors()->add('requested_amount', 'The amount is more than '.$multiplier.' times the member\'s savings ('.number_format($savings * $multiplier, 2).').');
            }

            if (! $this->group()->ensureGroupSetting()->allow_multiple_loans && $this->hasOpenLoan($memberId)) {
                $validator->errors()->add('group_member_id', 'This member already has a loan or an application in progress.');
            }
        }];
    }

    private function hasOpenLoan(int $memberId): bool
    {
        return $this->group()->loanApplications()->where('group_member_id', $memberId)->where('status', 'submitted')->exists()
            || $this->group()->loans()->where('group_member_id', $memberId)->whereIn('status', ['active', 'overdue'])->exists()
            || $this->group()->loanApplications()->where('group_member_id', $memberId)->where('status', 'approved')->doesntHave('loan')->exists();
    }
}
