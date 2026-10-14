<?php

namespace App\Http\Requests\Loans;

use App\Models\PendingEntry;
use App\Services\Loans\LoanService;
use App\Services\Loans\RepaymentService;
use App\Support\Wording;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

// only what the person typed. Any split sent along with it is never read: the server works the split out itself
class StoreRepaymentRequest extends FormRequest
{
    // before anything is read: someone out of reach gets a 404 whatever they sent
    public function authorize(): bool
    {
        $loan = $this->route('loan');

        $this->routeIs('agent.*')
            ? Gate::authorize('recordRepayment', [PendingEntry::class, $loan])
            : Gate::authorize('repay', $loan);

        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'paid_from' => ['required', 'string', 'in:' . app(RepaymentService::class)->paidFromOptions()->pluck('value')->implode(',')],
            'confirm_excess' => ['nullable', 'boolean'],
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
            'paid_from.required' => Wording::get(RepaymentService::PAID_FROM_INVALID),
            'paid_from.in' => Wording::get(RepaymentService::PAID_FROM_INVALID),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->has('amount') && (float) $this->input('amount') <= 0) {
                    $validator->errors()->add('amount', LoanService::AMOUNT_REFUSAL);
                }
            },
        ];
    }

    /** @return array{amount: string, date: string, paid_from: ?int, confirm_excess: bool, idempotency_key: ?string} */
    public function repayment(): array
    {
        $data = $this->validated();

        return [
            'amount' => $data['amount'],
            'date' => $data['date'],
            'paid_from' => app(RepaymentService::class)->accountIdFor($data['paid_from']),
            'confirm_excess' => (bool) ($data['confirm_excess'] ?? false),
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ];
    }
}
