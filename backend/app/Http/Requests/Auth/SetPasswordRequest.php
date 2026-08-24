<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SetPasswordRequest extends FormRequest
{
    // the route is gated by activation.pending, which has already checked who this is
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Those two passwords don\'t match yet. Type them again to be sure.',
        ];
    }
}
