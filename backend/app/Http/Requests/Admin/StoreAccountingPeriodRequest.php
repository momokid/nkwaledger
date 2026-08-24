<?php

namespace App\Http\Requests\Admin;

use App\Models\AccountingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAccountingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100', 'unique:accounting_periods,name'],
            'starts_on' => ['required', 'date'],
            'ends_on'   => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // a transaction dated in the overlap would have two possible homes
            $clash = AccountingPeriod::query()
                ->whereDate('starts_on', '<=', $this->input('ends_on'))
                ->whereDate('ends_on', '>=', $this->input('starts_on'))
                ->first();

            if ($clash) {
                $validator->errors()->add(
                    'starts_on',
                    "Those dates overlap {$clash->name}. Each day can only belong to one period.",
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.unique'              => 'A period with that name already exists.',
            'ends_on.after_or_equal'   => 'A period cannot end before it starts.',
        ];
    }
}
