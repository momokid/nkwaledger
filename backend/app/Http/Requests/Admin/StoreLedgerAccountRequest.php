<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:ledger_accounts,name'],
            'account_code' => ['nullable', 'string', 'max:50'],
            'control_id' => ['required', 'integer', 'exists:ledger_controls,id'],
            'subcategory_id' => ['required', 'integer', 'exists:ledger_subcategories,id'],
            'type_id' => ['required', 'integer', 'exists:ledger_types,id'],
        ];
    }
}
