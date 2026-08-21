<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The address the logo is fetched from.
 *
 * Whether anything usable lives there is not a question validation can answer,
 * so it only checks the shape. App\Support\Logo\RemoteLogo does the rest and
 * reports back in the same place.
 */
class LogoUrlRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Required, because pasting nothing and pressing save should say
            // so rather than appear to work. Removing the logo has its own
            // button.
            'logo_url' => ['required', 'url:https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo_url' => __('logo address'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo_url.required' => __('Paste the address the logo lives at.'),
            // Laravel's default names the rule rather than the reason, and the
            // reason here is worth saying: this image ends up on a document
            // that goes to clients.
            'logo_url.url' => __('That is not an https address. A logo fetched over plain http can be tampered with on the way here.'),
        ];
    }
}
