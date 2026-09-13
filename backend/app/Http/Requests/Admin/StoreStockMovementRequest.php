<?php

namespace App\Http\Requests\Admin;

use App\Enums\MovementReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                Rule::enum(MovementReason::class),
                // the starting count is written by the system, nobody picks it
                Rule::notIn([MovementReason::Opening->value]),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            // a miscount can go either way, so this one is told which
            'is_increase' => [
                Rule::requiredIf(fn() => $this->input('reason') === MovementReason::Correction->value),
                'nullable',
                'boolean',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $reason = MovementReason::from($this->input('reason'));
            $isIncrease = $reason->needsDirection()
                ? (bool) $this->input('is_increase')
                : $reason->addsToCount();

            // an addition is never checked against what is already there — only a
            // decrease can report more than the farm actually has on record
            if ($isIncrease) {
                return;
            }

            $stock = $this->route('stock');

            if ($stock === null) {
                return;
            }

            if ((float) $this->input('quantity') > (float) $stock->current_quantity) {
                $validator->errors()->add(
                    'quantity',
                    'That is more than the farm has on record. Please check the number.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please say what happened.',
            'reason.not_in' => 'Please choose what happened.',
            'quantity.required' => 'Please say how many.',
            'quantity.gt' => 'The number has to be more than zero.',
            'occurred_on.required' => 'Please say when it happened.',
            'occurred_on.before_or_equal' => 'The date cannot be in the future.',
            'is_increase.required' => 'Please say whether this adds or takes away.',
        ];
    }
}
