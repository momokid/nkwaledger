<?php

namespace App\Http\Requests\Admin;

use App\Models\FarmTypeCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        $farmCategoryId = FarmTypeCategory::where('name', $this->input('farm_category'))->value('id');

        return [
            'farm_category' => ['required', Rule::exists('farm_type_categories', 'name')],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('health_categories', 'name')->where('farm_type_category_id', $farmCategoryId),
            ],
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
