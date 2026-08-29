<?php

namespace App\Http\Requests\Admin;

use App\Enums\MovementReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
