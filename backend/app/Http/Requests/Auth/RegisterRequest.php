<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalisesPhone;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use NormalisesPhone;

    public function authorize(): bool
    {
        return true;
    }

    // runs before the rules, so uniqueness compares one spelling against one spelling
    protected function prepareForValidation(): void
    {
        $this->normalisePhoneField();
    }

    public function rules(): array
    {
        return [
            'surname'    => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'other_name' => ['nullable', 'string', 'max:100'],
            'phone'      => $this->phoneRules(['unique:users,phone']),
            'email'      => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'confirmed', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return array_merge($this->phoneMessages(), [
            'phone.unique' => 'That number is already registered. Try signing in instead.',
        ]);
    }
}
