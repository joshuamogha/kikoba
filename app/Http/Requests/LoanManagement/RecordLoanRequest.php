<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use Illuminate\Validation\Validator;

class RecordLoanRequest extends GroupScopedRequest
{
    /**
     * Leaders and the Treasurer record loans.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageLoans', $this->group());
    }

    /**
     * Get the validation rules that apply to the request. Recording a loan that already happened skips the eligibility
     * checks (savings cap, guarantors needed, one loan at a time) that apply to new applications.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_member_id' => ['required', 'integer', $this->inGroup('group_members', 'membership_status', 'active')],
            'loan_product_id' => ['required', 'integer', $this->inGroup('loan_products', 'status', 'active')],
            'requested_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'approved_amount' => ['nullable', 'numeric', 'gt:0', 'lte:requested_amount'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'purpose' => ['required', 'string', 'max:1000'],
            'application_date' => ['required', 'date', 'before_or_equal:disbursement_date'],
            'disbursement_date' => ['required', 'date', 'before_or_equal:today'],
            'guarantors' => ['nullable', 'array', 'max:20'],
            'guarantors.*.group_member_id' => ['required', 'integer', 'distinct', $this->inGroup('group_members', 'membership_status', 'active')],
            'guarantors.*.guaranteed_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
        ];
    }

    /**
     * A member cannot guarantee their own loan.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $product = $this->group()->loanProducts()->find($this->integer('loan_product_id'));
            $amount = (float) ($this->input('approved_amount') ?? $this->input('requested_amount'));

            if ($product?->hasRanges()) {
                if ($product->termFor($amount) === null) {
                    $validator->errors()->add('requested_amount', 'No loan range covers this amount.');
                }
            } elseif (! $this->filled('duration_months')) {
                $validator->errors()->add('duration_months', 'The duration is required.');
            }

            foreach ($this->input('guarantors', []) as $index => $guarantor) {
                if ((int) $guarantor['group_member_id'] === $this->integer('group_member_id')) {
                    $validator->errors()->add("guarantors.{$index}.group_member_id", 'A member cannot guarantee their own loan.');
                }
            }
        }];
    }
}
