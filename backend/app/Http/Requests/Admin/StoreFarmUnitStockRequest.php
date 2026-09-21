<?php

namespace App\Http\Requests\Admin;

use App\Enums\StockSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFarmUnitStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::enum(StockSource::class)],
            'opening_quantity' => ['required', 'numeric', 'gt:0'],
            'unit_of_measure' => ['nullable', 'string', 'max:30'],
            // this now posts as a real transaction amount, so it follows the same rule
            // every other PostingService-backed form does - a $0 double-entry line
            // proves nothing, so "not yet paid" is expressed with the credit toggle instead
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            // nothing can start on a day that has not happened yet
            'started_on' => ['required', 'date', 'before_or_equal:today'],
            'expected_ready_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            // only meaningful when source is "purchase" - an opening balance is never
            // bought, so it never offers a cash/credit choice
            'is_credit' => ['sometimes', 'boolean'],
            'settlement_account_id' => [
                'nullable',
                Rule::exists('ledger_accounts', 'id')
                    ->where('is_settlement', true)
                    ->where('is_active', true),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('source') || $this->input('source') !== StockSource::Purchase->value) {
                    return;
                }

                if (! $this->boolean('is_credit') && $this->input('settlement_account_id') === null) {
                    $validator->errors()->add('settlement_account_id', 'Please say where the money went.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'source.required' => 'Please say whether this was bought or already there.',
            'opening_quantity.required' => 'Please say how many there are.',
            'opening_quantity.gt' => 'The number has to be more than zero.',
            'acquisition_cost.required' => 'Please say what this is worth.',
            'acquisition_cost.gt' => 'The value has to be more than zero.',
            'started_on.required' => 'Please say when this started.',
            'started_on.before_or_equal' => 'The date cannot be in the future.',
            'expected_ready_on.date' => 'That does not look like a date.',
            'expected_ready_on.after_or_equal' => 'The ready date cannot be before the start date.',
            'settlement_account_id.exists' => 'Please choose where the money went.',
        ];
    }
}
