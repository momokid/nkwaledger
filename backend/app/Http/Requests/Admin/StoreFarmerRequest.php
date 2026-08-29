<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // matches the spelling the model stores, so the unique check cannot be sidestepped by formatting
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::normalise($this->input('phone')) ?? $this->input('phone')]);
        }
    }

    public function rules(): array
    {
        return [
            'surname' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'other_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', Rule::unique(User::class, 'phone')],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'home_address' => ['nullable', 'string', 'max:255'],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
            'farmer_group_id' => ['nullable', 'integer', Rule::exists('farmer_groups', 'id')->whereNull('deleted_at')],
            'assigned_agent_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'farm_type_ids' => ['required', 'array', 'min:1'],
            'farm_type_ids.*' => ['integer', Rule::exists('farm_types', 'id')->whereNull('deleted_at')],
        ];
    }

    // a farmer can only be handed to someone who actually works as an agent
    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('assigned_agent_id')) {
                    return;
                }

                $isAgent = User::query()
                    ->whereKey($this->input('assigned_agent_id'))
                    ->whereHas('roles', fn($query) => $query->where('name', 'agent'))
                    ->exists();

                if (! $isAgent) {
                    $validator->errors()->add('assigned_agent_id', 'Please choose someone who works as an agent.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number already belongs to an account. Try searching for the farmer instead.',
            'community_id.required' => 'Please choose the community this farmer works in.',
            'farm_type_ids.required' => 'Please choose at least one thing this farmer produces.',
            'farm_type_ids.min' => 'Please choose at least one thing this farmer produces.',
        ];
    }
}
