<?php

namespace App\Http\Requests\Admin;

use App\Models\FarmerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmUnitFromListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'farmer_uuid' => ['required', 'string', Rule::exists('farmer_profiles', 'uuid')->whereNull('deleted_at')],
            'farm_type_id' => ['required', 'integer', Rule::exists('farm_types', 'id')->whereNull('deleted_at')],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:30'],
        ];
    }

    // the name check needs a farmer to scope by, so it runs once the uuid is known to be real
    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $taken = $this->farmer()
                    ->farmUnits()
                    ->where('name', $this->input('name'))
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('name', 'This farmer already has a unit with that name.');
                }
            },
        ];
    }

    public function farmer(): FarmerProfile
    {
        return FarmerProfile::where('uuid', $this->input('farmer_uuid'))->firstOrFail();
    }

    public function messages(): array
    {
        return [
            'farmer_uuid.required' => 'Please choose which farmer this unit belongs to.',
            'farmer_uuid.exists' => 'We could not find that farmer.',
            'name.required' => 'Please give this unit a name, like Pen A.',
            'farm_type_id.required' => 'Please choose what is farmed here.',
            'community_id.required' => 'Please choose where this unit is.',
            'capacity.min' => 'The capacity cannot be less than zero.',
        ];
    }
}
