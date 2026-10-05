<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class SyncBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // shape only: whether a record may be posted is decided per record, so one bad record never sinks the batch
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1', 'max:50'],
            'records.*.uuid' => ['required', 'uuid'],
            'records.*.template' => ['required', 'integer'],
            'records.*.farmer' => ['required', 'uuid'],
            'records.*.farm_unit_id' => ['nullable', 'integer'],
            'records.*.amount' => ['required', 'string'],
            'records.*.settlement_account_id' => ['nullable', 'integer'],
            'records.*.is_credit' => ['nullable', 'boolean'],
            'records.*.quantity' => ['nullable', 'string'],
            'records.*.event_date' => ['required', 'date_format:Y-m-d'],
            'records.*.device_created_at' => ['required', 'date'],
            'records.*.supersedes' => ['nullable', 'uuid'],
        ];
    }
}
