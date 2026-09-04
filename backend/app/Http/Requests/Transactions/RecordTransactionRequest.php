<?php

namespace App\Http\Requests\Transactions;

use App\Models\FarmerProfile;
use App\Models\TransactionTemplate;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use App\Models\Transaction;

class RecordTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        $farmer = $this->farmer();

        return [
            'transaction_template_id' => [
                'required',
                Rule::exists('transaction_templates', 'id')->where('is_active', true),
                // a crop farmer has no animals to feed
                Rule::in($this->allowedTemplateIds($farmer)),
            ],
            'amount' => ['required', 'string'],
            'settlement_account_id' => [
                'nullable',
                Rule::exists('ledger_accounts', 'id')
                    ->where('is_settlement', true)
                    ->where('is_active', true),
            ],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'farm_unit_id' => [
                'nullable',
                // nobody records against another farmer's pen
                Rule::exists('farm_units', 'id')->where('farmer_profile_id', $farmer->id),
            ],
            'quantity_lost' => ['nullable', 'string'],
            'narration' => ['nullable', 'string', 'max:255'],
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
            // a loss always says how many, a crop bag count or a livestock head count
            function (Validator $validator) {
                if ($validator->errors()->has('transaction_template_id')) {
                    return;
                }

                $template = TransactionTemplate::find($this->input('transaction_template_id'));

                if ($template === null || $template->transaction_type !== Transaction::LOSS) {
                    return;
                }

                $quantity = $this->input('quantity_lost');

                if ($quantity === null || trim((string) $quantity) === '') {
                    $validator->errors()->add('quantity_lost', 'Please say how many were lost.');

                    return;
                }

                if (! is_numeric($quantity) || (float) $quantity <= 0) {
                    $validator->errors()->add('quantity_lost', 'The number lost needs to be more than zero.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_template_id.required' => 'Please choose what happened.',
            'transaction_template_id.in' => 'That kind of record does not match your farm.',
            'settlement_account_id.exists' => 'Please choose where the money went.',
            'transaction_date.before_or_equal' => 'That date has not happened yet.',
            'farm_unit_id.exists' => 'We could not find that part of the farm.',
        ];
    }

    // the farmer's own page names nobody, the agent's page names the farmer
    public function farmer(): FarmerProfile
    {
        $named = $this->route('farmer');

        if ($named instanceof FarmerProfile) {
            abort_if(
                ! $this->user()->hasRole('admin') && $named->assigned_agent_id !== $this->user()->id,
                404,
            );

            return $named;
        }

        $own = FarmerProfile::query()->where('user_id', $this->user()->id)->first();

        abort_if($own === null, 403);

        return $own;
    }

    private function allowedTemplateIds(FarmerProfile $farmer): array
    {
        return TransactionTemplate::query()
            ->where('is_active', true)
            // a farmer never cancels their own record
            ->where('transaction_type', '!=', Transaction::ADJUSTMENT)
            ->where(fn($query) => $query
                ->whereIn('farm_type_category_id', $farmer->farmTypes()->pluck('category_id'))
                // some things are true on every farm, so they belong to no category
                ->orWhereNull('farm_type_category_id'))
            ->pluck('id')
            ->all();
    }
}
