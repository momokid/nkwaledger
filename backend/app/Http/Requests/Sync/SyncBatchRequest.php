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
            // absent means an ordinary record
            'records.*.type' => ['nullable', 'in:health_report'],
            'records.*.template' => ['required_unless:records.*.type,health_report', 'integer'],
            'records.*.farmer' => ['required', 'uuid'],
            'records.*.farm_unit_id' => ['nullable', 'integer'],
            'records.*.amount' => ['required_unless:records.*.type,health_report', 'string'],
            // a health report's text is judged per record, like a record's amount, so it never sinks the batch
            'records.*.description' => ['nullable', 'string'],
            'records.*.settlement_account_id' => ['nullable', 'integer'],
            'records.*.is_credit' => ['nullable', 'boolean'],
            'records.*.quantity' => ['nullable', 'string'],
            // same limit as the web form (RecordTransactionRequest); trimming and empty-to-null come from the global input middleware
            'records.*.narration' => ['nullable', 'string', 'max:255'],
            'records.*.event_date' => ['required', 'date_format:Y-m-d'],
            'records.*.device_created_at' => ['required', 'date'],
            'records.*.supersedes' => ['nullable', 'uuid'],
        ];
    }
}
