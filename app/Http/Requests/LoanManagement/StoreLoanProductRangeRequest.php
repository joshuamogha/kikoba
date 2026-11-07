<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use App\Models\LoanManagement\LoanProductRange;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanProductRangeRequest extends GroupScopedRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loan_product_id' => ['required', 'integer', Rule::exists('loan_products', 'id')->where('group_id', $this->group()->id)],
            'minimum_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'maximum_amount' => ['nullable', 'numeric', 'gt:minimum_amount', 'max:9999999999999999'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:600'],
        ];
    }

    /**
     * Ranges of one product must not overlap; a range without an upper limit counts as running on forever.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $minimum = (float) $this->input('minimum_amount');
            $maximum = $this->filled('maximum_amount') ? (float) $this->input('maximum_amount') : INF;

            $overlaps = LoanProductRange::query()
                ->where('loan_product_id', $this->integer('loan_product_id'))
                ->when($this->route('loan_range'), fn ($query, $range) => $query->whereKeyNot($range->id))
                ->get()
                ->contains(fn (LoanProductRange $other) => $minimum <= ($other->maximum_amount === null ? INF : (float) $other->maximum_amount) && (float) $other->minimum_amount <= $maximum);

            if ($overlaps) {
                $validator->errors()->add('minimum_amount', 'This range overlaps another range of the same product.');
            }
        }];
    }
}
