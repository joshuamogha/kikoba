<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use App\Models\LoanManagement\Loan;
use Illuminate\Support\Carbon;
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
     * Either a single repayment (payment_date and amount) or several for the same loan (payments: date, amount, optional reference).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'loan_id' => ['required', 'integer', Rule::exists('loans', 'id')->where('group_id', $this->group()->id)->whereIn('status', ['active', 'overdue'])],
            'payment_method_id' => ['required', 'integer', $this->inGroup('payment_methods')],
        ];

        if ($this->has('payments')) {
            return [
                ...$rules,
                'payments' => ['required', 'array', 'min:1', 'max:50'],
                'payments.*.payment_date' => ['required', 'date', 'before_or_equal:today'],
                'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
                'payments.*.external_reference' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [
            ...$rules,
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The repayments of the request, earliest first.
     *
     * @return array<int, array{payment_date: string, amount: string|float|int, external_reference: string|null}>
     */
    public function entries(): array
    {
        $entries = $this->has('payments')
            ? array_map(fn (array $entry) => [
                'payment_date' => $entry['payment_date'],
                'amount' => $entry['amount'],
                'external_reference' => $entry['external_reference'] ?? null,
            ], $this->validated('payments'))
            : [['payment_date' => $this->validated('payment_date'), 'amount' => $this->validated('amount'), 'external_reference' => $this->validated('external_reference')]];

        usort($entries, fn (array $a, array $b) => strcmp($a['payment_date'], $b['payment_date']));

        return $entries;
    }

    /**
     * The repayments together cannot exceed what is still owed, and none can be dated before the loan was disbursed.
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
            $multiple = $this->has('payments');
            $entries = $multiple ? $this->input('payments') : [$this->only(['payment_date', 'amount'])];
            $total = array_sum(array_map(fn (array $entry) => (float) $entry['amount'], $entries));

            if (round($total, 2) > round((float) $loan->outstanding_balance, 2)) {
                $validator->errors()->add($multiple ? 'payments' : 'amount', ($multiple ? 'The amounts add up to '.number_format($total, 2).', which is more than' : 'The amount is more than').' the outstanding balance of '.number_format((float) $loan->outstanding_balance, 2).'.');
            }

            foreach ($entries as $index => $entry) {
                if (Carbon::parse($entry['payment_date'])->toDateString() < $loan->disbursement_date->toDateString()) {
                    $validator->errors()->add($multiple ? "payments.{$index}.payment_date" : 'payment_date', "The date is before the loan was disbursed ({$loan->disbursement_date->toDateString()}).");
                }
            }
        }];
    }
}
