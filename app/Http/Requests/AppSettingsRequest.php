<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The settings that are typed rather than uploaded. The logo has a form of its
 * own (AppSettingsLogoRequest), because a form carrying a file cannot sensibly
 * demand one and carry text fields that save without it.
 */
class AppSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Nullable throughout: these are Xolution's own details for the
            // PDF (SPEC §7), and the real values are still being collected.
            // Half-filled is a legitimate state - the template prints what is
            // there and leaves out what is not - and refusing to save until
            // all four arrive would just mean none of them get saved.
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_kvk' => ['nullable', 'string', 'max:50'],
            'company_vat_number' => ['nullable', 'string', 'max:50'],

            // Unlike the details above this one has no blank state: every
            // quote sent takes its expiry from it, and the column is not
            // nullable. "sometimes" is what lets the rest of the screen still
            // save a field at a time - the rule only applies to a submission
            // that carries it, and a submission that carries it empty is
            // refused rather than quietly read as zero days. Capped at a year,
            // well past any quote anyone means to honour.
            'default_validity_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * Named as the form labels them.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => __('company name'),
            'company_address' => __('address'),
            'company_kvk' => __('KvK number'),
            'company_vat_number' => __('BTW number'),
            'default_validity_days' => __('validity window'),
        ];
    }
}
