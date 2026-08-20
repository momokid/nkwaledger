<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers()->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Those two passwords don\'t match yet. Type them again to be sure.',
        ];
    }
}
