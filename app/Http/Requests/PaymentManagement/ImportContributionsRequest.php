<?php

namespace App\Http\Requests\PaymentManagement;

use Illuminate\Validation\Validator;

class ImportContributionsRequest extends GroupScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_fund_id' => ['required', 'integer', $this->inGroup('group_funds', 'status', 'active')],
            'payment_method_id' => ['nullable', 'integer', $this->inGroup('payment_methods')],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
        ];
    }

    /**
     * Share capital has its own import, because every share purchase needs a share record.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $fund = $this->group()->groupFunds()->with('fundType:id,code')->find($this->integer('group_fund_id'));

            if ($fund?->fundType?->code === 'SHR') {
                $validator->errors()->add('group_fund_id', 'Shares are imported on the Shares page, because each purchase needs a share record.');
            }
        }];
    }
}
