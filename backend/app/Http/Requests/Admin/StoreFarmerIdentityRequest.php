<?php

namespace App\Http\Requests\Admin;

use App\Enums\IdentityType;
use App\Models\FarmerProfile;
use App\Services\FarmerKycService;
use App\Support\IdentityDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFarmerIdentityRequest extends FormRequest
{
    // checked before validation, so an agent probing a farmer who is not theirs learns nothing
    // from "this document is already on another account"; a farmer out of scope is simply not there
    public function authorize(): bool
    {
        abort_unless(app(FarmerKycService::class)->maySubmitFor($this->route('farmer'), $this->user()), 404);

        return true;
    }

    public function rules(): array
    {
        return [
            'identity_type' => ['required', Rule::enum(IdentityType::class)],
            'identity_number' => ['required', 'string', 'min:6', 'max:30'],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    // the column holds a hash, so the clash is found by hashing the input and looking for a match
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $taken = FarmerProfile::query()
                    ->where('identity_type', $this->input('identity_type'))
                    ->where('identity_number_hash', IdentityDocument::hash($this->input('identity_number')))
                    ->whereKeyNot($this->route('farmer')->id)
                    ->exists();

                if ($taken) {
                    $validator->errors()->add(
                        'identity_number',
                        'This document is already on another farmer\'s account. Please check the number.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'identity_type.required' => 'Please choose which document this is.',
            'identity_number.required' => 'Please enter the number on the document.',
            'photo.required' => 'Please add a photo of the farmer.',
        ];
    }
}
