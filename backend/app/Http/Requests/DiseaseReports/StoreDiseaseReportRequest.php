<?php

namespace App\Http\Requests\DiseaseReports;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiseaseReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:1000'],
            // compressed on the way in, so the size ceiling here is about the
            // original phone photo, not what ends up stored
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Please describe what you are seeing.',
            'photo.required' => 'Please add one photo.',
            'photo.image' => 'That file does not look like a photo.',
            'photo.max' => 'That photo is too large. Please choose a smaller one.',
        ];
    }
}
