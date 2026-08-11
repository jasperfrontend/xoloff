<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price_ex_vat' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'tax_class_id' => ['required', 'integer', 'exists:tax_classes,id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],

            // Flexible key/value list, replaced wholesale on save.
            'specs' => ['array'],
            'specs.*.key' => ['required', 'string', 'max:255'],
            'specs.*.value' => ['required', 'string', 'max:255'],
        ];
    }
}
