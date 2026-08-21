<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxClassRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tax_classes', 'name')->ignore($this->route('tax_class')),
            ],
            // 0 is a legitimate rate - zero-rated / reverse charge (SPEC §3).
            // Four decimals, matching the column and the engine: anything
            // finer would be rounded on the way into the database, which is
            // the rounding this precision exists to prevent.
            'percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
        ];
    }
}
