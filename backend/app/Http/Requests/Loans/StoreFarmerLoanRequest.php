<?php

namespace App\Http\Requests\Loans;

use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Services\Loans\LoanService;
use App\Support\Money;
use Illuminate\Validation\Validator;

// the farmer's own web form: a new money loan also names the farms it will be used on. Agent, USSD and offline loans use the plain request
class StoreFarmerLoanRequest extends StoreLoanRequest
{
    public function rules(): array
    {
        $money = $this->input('kind') === Loan::KIND_MONEY;
        $mine = FarmerProfile::where('user_id', $this->user()?->id)->first()?->farmUnits()->pluck('uuid')->all() ?? [];
        $amount = ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'];

        return parent::rules() + ($money ? [
            'farms' => ['required', 'array', 'min:1'],
            'farms.*.farm_unit' => ['required', 'uuid', 'distinct', 'in:' . implode(',', $mine ?: ['-'])],
            'farms.*.amount' => $amount,
        ] : []);
    }

    public function messages(): array
    {
        $messages = parent::messages() + ['farms.required' => LoanService::FARMS_REFUSAL, 'farms.min' => LoanService::FARMS_REFUSAL];

        foreach (['required', 'uuid', 'distinct', 'in'] as $rule) {
            $messages["farms.*.farm_unit.{$rule}"] = LoanService::FARMS_REFUSAL;
        }

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
