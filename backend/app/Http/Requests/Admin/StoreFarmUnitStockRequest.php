<?php

namespace App\Http\Requests\Admin;

use App\Enums\StockSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'acquisition_cost' => ['required', 'numeric', 'min:0'],
            // nothing can start on a day that has not happened yet
            'started_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'source.required' => 'Please say whether this was bought or already there.',
            'opening_quantity.required' => 'Please say how many there are.',
            'opening_quantity.gt' => 'The number has to be more than zero.',
            'acquisition_cost.required' => 'Please say what it cost. Enter zero if nothing was paid.',
            'started_on.required' => 'Please say when this started.',
            'started_on.before_or_equal' => 'The date cannot be in the future.',
        ];
    }
}
