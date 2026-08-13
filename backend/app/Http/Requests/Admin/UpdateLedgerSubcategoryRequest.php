<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLedgerSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:ledger_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ledger_subcategories', 'name')
                    ->where('category_id', $this->input('category_id'))
                    ->ignore($this->route('ledgerSubcategory')),
            ],
        ];
    }
}
