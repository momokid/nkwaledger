<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_registration_number' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:suppliers,email'],
        ];
    }
}
