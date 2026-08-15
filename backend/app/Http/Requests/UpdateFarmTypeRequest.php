<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmTypeRequest extends FormRequest
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
                Rule::unique('farm_types', 'name')->ignore($this->route('farmType')),
            ],
            'category_id' => ['nullable', 'integer', 'exists:farm_type_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
