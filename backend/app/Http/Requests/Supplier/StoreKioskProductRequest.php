<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreKioskProductRequest extends FormRequest
{
    // open item #8: no limit was ever agreed, so these are a starting default,
    // easy to revisit once someone actually decides
    public const MAX_IMAGE_KB = 5120;

    public const MAX_IMAGES = 3;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raw_code' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'pack_quantity' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'integer', 'min:1'],
            'expiry_date' => ['nullable', 'date'],
            'images' => ['nullable', 'array', 'max:' . self::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:' . self::MAX_IMAGE_KB],
        ];
    }
}
