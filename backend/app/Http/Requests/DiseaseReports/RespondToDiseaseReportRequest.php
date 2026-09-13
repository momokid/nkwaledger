<?php

namespace App\Http\Requests\DiseaseReports;

use App\Enums\ContactMethod;
use App\Enums\DiseaseReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondToDiseaseReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // "new" is where every report starts, not something to respond back into
            'status' => ['required', Rule::enum(DiseaseReportStatus::class)->except(DiseaseReportStatus::New)],
            'contact_method' => ['required', Rule::enum(ContactMethod::class)],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Please choose what happens next.',
            'contact_method.required' => 'Please say how you reached the farmer.',
            'note.required' => 'Please add a short note.',
        ];
    }
}
