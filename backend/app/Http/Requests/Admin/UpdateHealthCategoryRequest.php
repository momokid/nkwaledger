<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHealthCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        $category = $this->route('healthCategory');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('health_categories', 'name')
                    ->where('farm_type_category_id', $category->farm_type_category_id)
                    ->ignore($category->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter a category name.',
            'name.max' => 'The name can be at most 100 characters.',
            'name.unique' => 'This name is already used in this farm category.',
        ];
    }
}
