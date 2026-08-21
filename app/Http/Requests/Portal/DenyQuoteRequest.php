<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The optional note a customer leaves when declining (SPEC §8).
 */
class DenyQuoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional, because SPEC §8 opens a reason box rather than
            // demanding one. Bounded because this is the only field in xoloff
            // a stranger can write to, and it is read back on a page.
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('toelichting'),
        ];
    }

    /**
     * The customer reads these, so they are in Dutch like the rest of the
     * portal rather than in the English the two internal users see.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => __('Houd de toelichting korter dan 2000 tekens.'),
        ];
    }
}
