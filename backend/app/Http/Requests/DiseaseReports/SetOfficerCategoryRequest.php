<?php

namespace App\Http\Requests\DiseaseReports;

use App\Models\HealthCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetOfficerCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        $farmCategoryId = $this->route('report')->farmUnit->farmType->category_id;

        return [
            'health_category' => ['required', 'uuid', Rule::exists('health_categories', 'uuid')
                ->where('farm_type_category_id', $farmCategoryId)
                ->where('is_active', true)],
            'rejected' => ['sometimes', 'boolean'],
        ];
    }

    public function category(): HealthCategory
    {
        return HealthCategory::where('uuid', $this->validated('health_category'))->firstOrFail();
    }
}
