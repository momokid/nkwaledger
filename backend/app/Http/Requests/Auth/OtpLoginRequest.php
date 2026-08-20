<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalisesPhone;
use Illuminate\Foundation\Http\FormRequest;

class OtpLoginRequest extends FormRequest
{
    use NormalisesPhone;

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
            'phone' => $this->phoneRules(),
        ];
    }

    public function messages(): array
    {
        return $this->phoneMessages();
    }
}
