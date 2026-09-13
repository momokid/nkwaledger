<?php

namespace App\Http\Requests\Admin;

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOfficerAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'officer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::enum(OfficerRole::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $agent = User::find($this->input('agent_id'));

                if (! $agent->hasRole('agent')) {
                    $validator->errors()->add('agent_id', 'Please choose an agent.');
                }

                $officer = User::find($this->input('officer_id'));

                if (! $officer->hasRole($this->input('role'))) {
                    $validator->errors()->add('officer_id', 'That person does not hold this role.');
                }

                $exists = AgentOfficerAssignment::query()
                    ->where('agent_id', $this->input('agent_id'))
                    ->where('officer_id', $this->input('officer_id'))
                    ->where('role', $this->input('role'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('officer_id', 'This agent is already linked to this officer for this role.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'agent_id.required' => 'Please choose an agent.',
            'officer_id.required' => 'Please choose an officer.',
            'role.required' => 'Please choose which kind of officer this is.',
        ];
    }
}
