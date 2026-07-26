<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFarmerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:farmer_groups,name'],
            'group_type_id' => ['nullable', 'integer', 'exists:farmer_group_types,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'description' => ['nullable', 'string'],
            'is_shared_liability' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    // created_by is always the authenticated user, never trusted from the request body —
    // this is what makes the "created_by is set automatically" test pass regardless of what's submitted
    public function validated($key = null, $default = null): array
    {
        return array_merge(parent::validated($key, $default), [
            'created_by' => $this->user()->id,
        ]);
    }
}
