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

    /**
     * Named as the form labels them. Without this the spec rows report as
     * "specs.0.key", which is the shape of the payload rather than anything on
     * screen.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'price_ex_vat' => __('price excluding VAT'),
            'tax_class_id' => __('default tax class'),
            'category_id' => __('category'),
            'specs.*.key' => __('specification name'),
            'specs.*.value' => __('specification value'),
        ];
    }
}
