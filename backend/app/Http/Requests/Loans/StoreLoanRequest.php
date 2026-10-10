<?php

namespace App\Http\Requests\Loans;

use App\Models\Loan;
use App\Services\Loans\LoanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanRequest extends FormRequest
{
    public function rules(): array
    {
        $money = $this->input('kind') === Loan::KIND_MONEY;

        return [
            'kind' => ['required', Rule::in([Loan::KIND_MONEY, Loan::KIND_EXISTING])],
            'lender_name' => ['required', 'string', 'max:255'],
            'lender_type' => ['required', Rule::in(Loan::LENDER_TYPES)],
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            'date' => [$money ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'paid_into' => [
                $money ? 'required' : 'nullable',
                'integer',
                Rule::in(app(LoanService::class)->paidIntoOptions()->pluck('id')->all()),
            ],
            'interest' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            // only a loan received has a date received to compare it with; an opening entry may already be overdue
            'due_date' => $money ? ['nullable', 'date', 'after_or_equal:date'] : ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            // client-generated, so a form sent twice makes one loan
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => LoanService::AMOUNT_REFUSAL,
            'amount.string' => LoanService::AMOUNT_REFUSAL,
            'amount.regex' => LoanService::AMOUNT_REFUSAL,
            'date.before_or_equal' => LoanService::FUTURE_REFUSAL,
            'due_date.after_or_equal' => 'The due date cannot be before the date received.',
            'lender_name.required' => "Enter the lender's name.",
            'lender_type.required' => 'Choose the lender type.',
            'lender_type.in' => 'Choose the lender type.',
            'paid_into.required' => 'Choose where the money was paid into.',
            'paid_into.integer' => 'Choose where the money was paid into.',
            'paid_into.in' => 'Choose where the money was paid into.',
        ];
    }

    public function after(): array
    {
        return [
            // a zero amount reads fine as a number but is not an amount
            function (Validator $validator) {
                if ($validator->errors()->has('amount')) {
                    return;
                }

                if ((float) $this->input('amount') <= 0) {
                    $validator->errors()->add('amount', LoanService::AMOUNT_REFUSAL);
                }
            },
        ];
    }
}
