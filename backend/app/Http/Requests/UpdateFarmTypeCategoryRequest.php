<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmTypeCategoryRequest extends FormRequest
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
                Rule::unique('farm_type_categories', 'name')->ignore($this->route('farmTypeCategory')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
