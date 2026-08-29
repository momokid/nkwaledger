<?php

namespace App\Http\Requests\Admin;

use App\Models\FarmUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FarmUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'farm_type_id' => ['required', 'integer', Rule::exists('farm_types', 'id')->whereNull('deleted_at')],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
            'name' => [
                'required',
                'string',
                'max:100',
                // one farm cannot have two pens with the same name
                Rule::unique('farm_units', 'name')
                    ->where('farmer_profile_id', $this->route('farmer')->id)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('farmUnit')?->id),
            ],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please give this unit a name, like Pen A.',
            'name.unique' => 'This farmer already has a unit with that name.',
            'farm_type_id.required' => 'Please choose what is farmed here.',
            'community_id.required' => 'Please choose where this unit is.',
            'capacity.min' => 'The capacity cannot be less than zero.',
        ];
    }
}
