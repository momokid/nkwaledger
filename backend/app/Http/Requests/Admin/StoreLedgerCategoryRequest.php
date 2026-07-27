<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fundamental_type_id' => ['required', 'integer', 'exists:ledger_fundamental_types,id'],
            'name' => ['required', 'string', 'max:255', 'unique:ledger_categories,name'],
            'type' => ['required', 'string', 'max:255'],
        ];
    }
}
