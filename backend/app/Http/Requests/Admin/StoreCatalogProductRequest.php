<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode' => ['nullable', 'string', 'max:255', 'unique:catalog_products,barcode'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'pack_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
