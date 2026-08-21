<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendQuoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The per-quote leeway from SPEC §7. Optional: leaving it alone
            // means this quote follows the application default, which is what
            // most of them do. The bounds match the default's own, so the two
            // cannot disagree about what a sane window is.
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'validity_days' => __('validity window'),
        ];
    }
}
