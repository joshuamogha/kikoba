<?php

namespace App\Http\Requests\LoanManagement;

use App\Http\Requests\PaymentManagement\GroupScopedRequest;
use App\Models\LoanManagement\LoanApplication;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanGuarantorRequest extends GroupScopedRequest
{
    /**
     * Leaders and the Treasurer, or the applicant, may add guarantors.
     */
    public function authorize(): bool
    {
        if ($this->user()->can('manageLoans', $this->group())) {
            return true;
        }

        return $this->group()->loanApplications()
            ->whereKey($this->integer('loan_application_id'))
            ->whereHas('groupMember', fn ($member) => $member->where('user_id', $this->user()->id))
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loan_application_id' => ['required', 'integer', Rule::exists('loan_applications', 'id')->where('group_id', $this->group()->id)->where('status', 'submitted')],
            'group_member_id' => [
                'required', 'integer',
                $this->inGroup('group_members', 'membership_status', 'active'),
                Rule::unique('loan_guarantors', 'group_member_id')->where('loan_application_id', $this->integer('loan_application_id')),
            ],
            'guaranteed_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
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

            $application = LoanApplication::query()->findOrFail($this->integer('loan_application_id'));

            if ($application->group_member_id === $this->integer('group_member_id')) {
                $validator->errors()->add('group_member_id', 'A member cannot guarantee their own loan.');
            }
        }];
    }
}
