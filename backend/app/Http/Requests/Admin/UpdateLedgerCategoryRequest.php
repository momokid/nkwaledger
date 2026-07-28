<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLedgerCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ledger_categories', 'name')->ignore($this->route('ledgerCategory')),
            ],
            'class_id' => ['required', 'integer', 'exists:ledger_classes,id'],
        ];
    }
}
