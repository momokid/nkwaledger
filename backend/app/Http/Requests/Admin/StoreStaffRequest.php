<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalisesPhone;
use App\Services\StaffInvitationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    use NormalisesPhone;

    // the route already gates this behind staff.create and a fresh password confirmation
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalisePhoneField();
    }

    public function rules(): array
    {
        return [
            'surname'    => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'other_name' => ['nullable', 'string', 'max:255'],
            'phone'      => $this->phoneRules(['unique:users,phone']),
            'email'      => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role'       => ['required', Rule::in(StaffInvitationService::INVITABLE_ROLES)],
        ];
    }

    public function messages(): array
    {
        return array_merge($this->phoneMessages(), [
            'phone.unique' => 'Someone is already registered on that number.',
            'email.unique' => 'Someone is already registered on that email.',
            'role.in'      => 'Pick one of agent, vet, adviser or supplier. Farmers register themselves, and admins are set up separately.',
        ]);
    }
}
