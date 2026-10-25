<?php

namespace App\Http\Requests\Loans;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

// "I used some of it for something else": an amount and what it was used for, on the farmer's own loan
class StoreOtherUseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // a loan out of reach does not exist for the caller: the policy answers 404
        return Gate::forUser($this->user())->authorize('repay', $this->route('loan'))->allowed();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'string'],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount needs to be more than zero.',
            'note.required' => 'Please say what you used it for.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('amount')) {
                return;
            }

            try {
                $minor = Money::toMinor($this->input('amount'));
            } catch (InvalidArgumentException) {
                $minor = 0;
            }

            // "no more than what is left" is checked in the controller, under a lock on the loan
            if ($minor <= 0) {
                $validator->errors()->add('amount', 'The amount needs to be more than zero.');
            }
        }];
    }

    public function amountMinor(): int
    {
        return Money::toMinor($this->validated('amount'));
    }
}
