<?php

namespace App\Http\Requests\Loans;

use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Models\TransactionTemplate;
use App\Services\Loans\LoanService;
use App\Support\Money;
use Illuminate\Validation\Validator;

// a new money loan also names the farms it will be used on: from the farmer's own web form, or from the agent's form
// (the farmer is then named in the address). USSD and offline loans use the plain request
class StoreFarmerLoanRequest extends StoreLoanRequest
{
    public function rules(): array
    {
        $money = $this->input('kind') === Loan::KIND_MONEY;
        $mine = $this->farmer()?->farmUnits()->pluck('uuid')->all() ?? [];
        $purposes = $this->farmer() ? TransactionTemplate::loanPurposesFor($this->farmer())->pluck('slug')->all() : [];
        $amount = ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'];

        return parent::rules() + ($money ? [
            'farms' => ['required', 'array', 'min:1'],
            'farms.*.farm_unit' => ['required', 'uuid', 'distinct', 'in:' . implode(',', $mine ?: ['-'])],
            'farms.*.amount' => $amount,
            'farms.*.purpose' => ['required', 'string', 'in:' . implode(',', [LoanService::OTHER_PURPOSE, ...$purposes])],
            'farms.*.purpose_note' => ['required_if:farms.*.purpose,' . LoanService::OTHER_PURPOSE, 'nullable', 'string', 'max:255'],
        ] : []);
    }

    // the farmer the loan is for: named in the agent's address, otherwise the signed-in farmer
    private function farmer(): ?FarmerProfile
    {
        $named = $this->route('farmer');

        return $named instanceof FarmerProfile ? $named : FarmerProfile::where('user_id', $this->user()?->id)->first();
    }

    public function messages(): array
    {
        $messages = parent::messages() + ['farms.required' => LoanService::FARMS_REFUSAL, 'farms.min' => LoanService::FARMS_REFUSAL];

        foreach (['required', 'uuid', 'distinct', 'in'] as $rule) {
            $messages["farms.*.farm_unit.{$rule}"] = LoanService::FARMS_REFUSAL;
        }

        foreach (['required', 'string', 'in'] as $rule) {
            $messages["farms.*.purpose.{$rule}"] = LoanService::PURPOSE_REFUSAL;
        }

        $messages['farms.*.purpose_note.required_if'] = LoanService::PURPOSE_NOTE_REFUSAL;

        foreach (['required', 'string', 'regex'] as $rule) {
            $messages["farms.*.amount.{$rule}"] = LoanService::AMOUNT_REFUSAL;
        }

        return $messages;
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator) {
                if ($this->input('kind') !== Loan::KIND_MONEY || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $parts = array_map(fn($row) => Money::toMinor((string) $row['amount']), $this->input('farms'));

                if (in_array(0, $parts, true)) {
                    $validator->errors()->add('farms', LoanService::AMOUNT_REFUSAL);
                } elseif (array_sum($parts) !== Money::toMinor((string) $this->input('amount'))) {
                    $validator->errors()->add('farms', LoanService::FARMS_REFUSAL);
                }
            },
        ];
    }
}
