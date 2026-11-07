<?php

namespace App\Http\Requests\ShareManagement;

use App\Models\ShareManagement\MemberShareTransaction;
use App\Support\BillingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGroupShareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageShareOuts', $this->route('group'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_member_id' => [
                'required', 'integer',
                Rule::exists('group_members', 'id')
                    ->where('group_id', $this->route('group')->id)
                    ->where('membership_status', 'active'),
            ],
            'number_of_shares' => ['required', 'integer', 'min:1', 'max:1000000'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'period' => BillingPeriod::rules(),
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The group's share price must be set, and the member's shares for the month must stay within the group's share limits.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $setting = $this->route('group')->ensureGroupSetting();

            if ((float) $setting->share_price <= 0) {
                $validator->errors()->add('number_of_shares', 'Set the share price in the group settings first.');

                return;
            }

            $period = BillingPeriod::start($this->input('period'), Carbon::parse($this->input('transaction_date')));

            $held = (int) MemberShareTransaction::query()
                ->where('group_member_id', $this->integer('group_member_id'))
                ->where('transaction_type', 'purchase')
                ->whereDate('period_start', $period->toDateString())
                ->sum('number_of_shares');
            $total = $held + $this->integer('number_of_shares');

            if ($total < $setting->minimum_shares) {
                $validator->errors()->add('number_of_shares', "A member must buy at least {$setting->minimum_shares} shares in {$period->format('F Y')} (currently {$held}).");
            } elseif ($setting->maximum_shares !== null && $total > $setting->maximum_shares) {
                $validator->errors()->add('number_of_shares', "A member can buy at most {$setting->maximum_shares} shares in {$period->format('F Y')} (currently {$held}).");
            }
        }];
    }
}
