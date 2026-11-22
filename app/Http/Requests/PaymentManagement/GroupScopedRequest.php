<?php

namespace App\Http\Requests\PaymentManagement;

use App\Actions\ShareManagement\RecordSharePurchase;
use App\Models\PaymentManagement\GroupFund;
use App\Models\UserManagement\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * Base for payment-management writes: the group comes from the group_id input and only its leaders and Treasurer may write.
 */
abstract class GroupScopedRequest extends FormRequest
{
    private ?Group $resolvedGroup = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('managePayments', $this->group());
    }

    /**
     * Codes are stored in upper case, so compare them in upper case.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * The group the request writes to.
     */
    public function group(): Group
    {
        return $this->resolvedGroup ??= Group::query()->findOrFail($this->integer('group_id'));
    }

    /**
     * An amount for the Shares fund must buy a whole number of shares at the group's share price. Checks the items of a single
     * contribution and the items of every row of a bulk request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lists = ['items' => $this->input('items')];

            foreach ((array) $this->input('rows') as $row => $data) {
                $lists["rows.{$row}.items"] = $data['items'] ?? null;
            }

            $price = (float) $this->group()->ensureGroupSetting()->share_price;

            foreach ($lists as $prefix => $items) {
                foreach (is_array($items) ? $items : [] as $index => $item) {
                    $isSharesFund = GroupFund::query()->whereKey($item['group_fund_id'] ?? 0)
                        ->whereHas('fundType', fn ($type) => $type->where('code', 'SHR'))->exists();

                    if ($isSharesFund && $price > 0 && app(RecordSharePurchase::class)->sharesFor($this->group(), (float) $item['amount']) === null) {
                        $validator->errors()->add("{$prefix}.{$index}.amount", 'The Shares amount must be a whole number of shares at the share price of '.number_format($price, 2).'.');
                    }
                }
            }
        }];
    }

    /**
     * A rule for an id that must belong to this group and be active.
     */
    protected function inGroup(string $table, string $activeColumn = 'is_active', mixed $activeValue = true): Exists
    {
        return Rule::exists($table, 'id')
            ->where('group_id', $this->group()->id)
            ->where($activeColumn, $activeValue);
    }
}
