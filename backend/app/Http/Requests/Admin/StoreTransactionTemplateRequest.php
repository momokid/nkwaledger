<?php

namespace App\Http\Requests\Admin;

use App\Models\TransactionTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreTransactionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('transaction_templates', 'slug')->whereNull('deleted_at'),
            ],
            'transaction_type' => ['required', Rule::in(TransactionTemplate::TYPES)],
            'debit_account_id' => ['required', $this->activeAccountRule()],
            'credit_account_id' => ['required', 'different:debit_account_id', $this->activeAccountRule()],
            'settlement_side' => ['required', Rule::in(TransactionTemplate::SETTLEMENT_SIDES)],
            'requires_farm_unit' => ['boolean'],
            'farm_type_category_id' => [
                'nullable',
                Rule::exists('farm_type_categories', 'id')->whereNull('deleted_at'),
            ],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give this entry a name farmers will recognise.',
            'slug.required' => 'A short code is needed so the system can find this template.',
            'slug.regex' => 'The short code can only use small letters, numbers and underscores.',
            'slug.unique' => 'That short code is already in use by another template.',
            'transaction_type.required' => 'Choose whether this is income, an expense, a loss or an adjustment.',
            'transaction_type.in' => 'That transaction type is not one we recognise.',
            'debit_account_id.required' => 'Choose the account that receives the value.',
            'debit_account_id.exists' => 'That account is either missing or no longer active.',
            'credit_account_id.required' => 'Choose the account that gives up the value.',
            'credit_account_id.different' => 'The two accounts must be different, because value cannot move from an account into itself.',
            'credit_account_id.exists' => 'That account is either missing or no longer active.',
            'settlement_side.required' => 'Say which side the cash, mobile money or bank choice replaces.',
            'settlement_side.in' => 'That settlement side is not one we recognise.',
            'farm_type_category_id.exists' => 'That farm type category could not be found.',
        ];
    }

    // a posting rule may only point at an account that is live and not soft deleted
    protected function activeAccountRule(): Exists
    {
        return Rule::exists('ledger_accounts', 'id')
            ->where(fn($query) => $query->where('is_active', true)->whereNull('deleted_at'));
    }
}
