<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmerGroupRequest extends FormRequest
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
                Rule::unique('farmer_groups', 'name')->ignore($this->route('farmerGroup')),
            ],
            'group_type_id' => ['nullable', 'integer', 'exists:farmer_group_types,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'description' => ['nullable', 'string'],
            'is_shared_liability' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
