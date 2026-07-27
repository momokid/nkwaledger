<?php

namespace App\Http\Requests;

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
            'type_id' => ['nullable', 'integer', 'exists:ledger_account_types,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
