<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gender' => ['nullable', 'string', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
            'farmer_group_id' => ['nullable', 'integer', Rule::exists('farmer_groups', 'id')->whereNull('deleted_at')],
            'farm_type_ids' => ['required', 'array', 'min:1'],
            'farm_type_ids.*' => ['integer', Rule::exists('farm_types', 'id')->whereNull('deleted_at')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'community_id.required' => 'Please choose the community this farmer lives in.',
            'farm_type_ids.required' => 'Please choose at least one thing this farmer produces.',
            'farm_type_ids.min' => 'Please choose at least one thing this farmer produces.',
        ];
    }
}
