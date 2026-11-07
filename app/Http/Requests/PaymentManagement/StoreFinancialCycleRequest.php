<?php

namespace App\Http\Requests\PaymentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

class StoreFinancialCycleRequest extends GroupScopedRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contribution_frequency_id' => ['required', 'integer', $this->inGroup('contribution_frequencies')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * A month belongs to exactly one cycle, so a cycle may not overlap another cycle of the group.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $current = $this->route('financial_cycle');

            $clash = $this->group()->financialCycles()
                ->when($current !== null, fn ($query) => $query->whereKeyNot($current instanceof Model ? $current->getKey() : $current))
                ->whereDate('start_date', '<=', $this->date('end_date'))
                ->whereDate('end_date', '>=', $this->date('start_date'))
                ->orderBy('start_date')
                ->first();

            if ($clash !== null) {
                $validator->errors()->add('start_date', "These dates overlap \"{$clash->name}\" ({$clash->start_date->format('M Y')} - {$clash->end_date->format('M Y')}). Edit or remove that cycle first.");
            }
        }];
    }
}
