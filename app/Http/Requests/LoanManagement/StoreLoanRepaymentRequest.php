<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use App\Models\LoanManagement\Loan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanRepaymentRequest extends GroupScopedRequest
{
    /**
     * Leaders and the Treasurer record repayments.
     */
    public function authorize(): bool
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
        return [
            'loan_id' => ['required', 'integer', Rule::exists('loans', 'id')->where('group_id', $this->group()->id)->whereIn('status', ['active', 'overdue'])],
            'payment_method_id' => ['required', 'integer', $this->inGroup('payment_methods')],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A repayment cannot exceed what is still owed.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $loan = Loan::query()->findOrFail($this->integer('loan_id'));

            if ((float) $this->input('amount') > (float) $loan->outstanding_balance) {
                $validator->errors()->add('amount', 'The amount is more than the outstanding balance of '.number_format((float) $loan->outstanding_balance, 2).'.');
            }
        }];
    }
}
