<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLedgerAccountRequest extends FormRequest
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
                Rule::unique('ledger_accounts', 'name')->ignore($this->route('ledgerAccount')),
            ],
            'account_code' => ['nullable', 'string', 'max:50'],
            'control_id' => ['required', 'integer', 'exists:ledger_controls,id'],
            'subcategory_id' => ['required', 'integer', 'exists:ledger_subcategories,id'],
            'type_id' => ['required', 'integer', 'exists:ledger_types,id'],
            'is_active' => ['boolean'],
        ];
    }
}
