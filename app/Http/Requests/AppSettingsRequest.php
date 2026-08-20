<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Required, because uploading a logo is the only thing this
            // screen currently does, and submitting it with no file chosen
            // should say so rather than appear to save. When M4 adds the
            // validity window to this form, this becomes optional again.
            //
            // No SVG: it is a document that can carry script, and this file is
            // rendered by a real Chromium while the PDF is produced. A raster
            // logo is also what a print-resolution PDF wants. Both "image" and
            // the mime list refuse it independently, which is deliberate - the
            // day someone reaches for image:allow_svg, the mime list still
            // holds.
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo' => __('logo'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => __('Choose an image file to use as the logo.'),

            // Laravel's default here is "The logo failed to upload", which is
            // true and useless: it covers a file that was too big for PHP and a
            // server that could not write its temporary file, and points at
            // neither. On Windows the second one happens whenever the dev
            // server is launched from a shell with no TMP, because PHP then
            // falls back to C:\WINDOWS and cannot write there.
            'logo.uploaded' => __('The logo could not be uploaded. Either it is larger than the server accepts, or the server had nowhere to store it while it arrived.'),
            'logo.mimes' => __('Upload the logo as a PNG, JPG or WebP file.'),
            'logo.max' => __('Keep the logo under 2 MB.'),
        ];
    }
}
