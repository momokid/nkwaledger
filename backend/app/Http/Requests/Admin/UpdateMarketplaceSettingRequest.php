<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketplaceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // nullable/blank on purpose - this is the only way admin can clear a
            // setting back to empty (e.g. turning off the Market Center announcement)
            'value' => ['nullable', 'string', 'max:500'],
        ];
    }
}
