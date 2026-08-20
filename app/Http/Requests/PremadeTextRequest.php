<?php

namespace App\Http\Requests;

use App\Enums\PremadeTextKey;
use App\Support\Html\RichText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Both texts are edited on one screen and saved together, so the request
 * carries a value per key rather than a single content field.
 */
class PremadeTextRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intro' => ['nullable', 'string', 'max:20000'],

            // The footer holds the mandatory legal disclaimer, so unlike the
            // intro it cannot be left blank (SPEC §3).
            'footer' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'intro' => __('intro text'),
            'footer' => __('footer text'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'footer.required' => __('The footer carries the legal disclaimer, so it cannot be empty.'),
        ];
    }

    /**
     * Cleaned before validation rather than after, so "required" judges what
     * will actually be stored: an editor that has been emptied still submits a
     * stray paragraph tag, and that must not pass as a filled-in footer.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            PremadeTextKey::Intro->value => RichText::sanitize((string) $this->input(PremadeTextKey::Intro->value, '')),
            PremadeTextKey::Footer->value => RichText::sanitize((string) $this->input(PremadeTextKey::Footer->value, '')),
        ]);
    }
}
