<?php

namespace App\Http\Requests\Transactions;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class SettleTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string'],
            // where the payment actually landed - Receivable/Payable is what is being
            // paid down, never where the money itself sits
            'settlement_account_id' => [
                'required',
                Rule::exists('ledger_accounts', 'id')
                    ->where('is_settlement', true)
                    ->where('is_active', true)
                    ->where(fn($query) => $query
                        ->whereNotIn('name', ['Accounts Receivable', 'Accounts Payable'])),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('amount')) {
                    return;
                }

                try {
                    $minor = Money::toMinor($this->input('amount'));
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('amount', 'Please write the amount in cedis, like 250.75');

                    return;
                }

                if ($minor <= 0) {
                    $validator->errors()->add('amount', 'The amount needs to be more than zero.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'settlement_account_id.required' => 'Please say where the money landed.',
            'settlement_account_id.exists' => 'Please choose where the money landed.',
        ];
    }
}
