<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The two addresses the logo is fetched from.
 *
 * Both are optional and both are allowed to be empty, because clearing an
 * address is how that logo is removed. Whether anything usable lives at either
 * one is not a question validation can answer - App\Support\Logo\RemoteLogo
 * does that and reports back against the same field.
 */
class LogoUrlRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo_vector_url' => ['nullable', 'url:https', 'max:2048'],
            'logo_raster_url' => ['nullable', 'url:https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo_vector_url' => __('SVG address'),
            'logo_raster_url' => __('PNG or JPG address'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        // Laravel's default names the rule rather than the reason, and the
        // reason is worth saying: these images end up on documents and in
        // messages that go to clients.
        return [
            'logo_vector_url.url' => __('That is not an https address. A logo fetched over plain http can be tampered with on the way here.'),
            'logo_raster_url.url' => __('That is not an https address. A logo fetched over plain http can be tampered with on the way here.'),
        ];
    }
}
