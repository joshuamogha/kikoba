<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use App\Models\LoanManagement\LoanRepayment;
use Illuminate\Validation\Validator;

class UpdateLoanRepaymentRequest extends GroupScopedRequest
{
    /**
     * The repayment must belong to the group the request names, and only its leaders and Treasurer may change it.
     */
    public function authorize(): bool
    {
        abort_unless($this->integer('group_id') === $this->repayment()->loan->group_id, 404);

        return $this->user()->can('manageLoans', $this->group());
    }

    /**
     * The loan cannot be changed, only the payment itself.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'integer', $this->inGroup('payment_methods')],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Not before the loan was disbursed, and the loan's repayments together cannot be more than it has to pay back: a pending
     * repayment than what is still owed, an approved one than the total payable once its own amount is replaced.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $repayment = $this->repayment();
            $loan = $repayment->loan;
            $amount = round((float) $this->input('amount'), 2);

            if ($this->date('payment_date')->toDateString() < $loan->disbursement_date->toDateString()) {
                $validator->errors()->add('payment_date', "The date is before the loan was disbursed ({$loan->disbursement_date->toDateString()}).");
            }

            $limit = $repayment->status === 'approved'
                ? round((float) $loan->total_payable - (float) $loan->loanRepayments()->where('status', 'approved')->whereKeyNot($repayment->id)->sum('amount'), 2)
                : round((float) $loan->outstanding_balance, 2);

            if ($amount > $limit) {
                $validator->errors()->add('amount', 'The amount is more than the '.($repayment->status === 'approved' ? 'loan still has room for' : 'outstanding balance of').' '.number_format($limit, 2).'.');
            }
        }];
    }

    private function repayment(): LoanRepayment
    {
        return $this->route('loan_repayment');
    }
}
