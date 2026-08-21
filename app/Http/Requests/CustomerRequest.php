<?php

namespace App\Http\Requests;

use App\Enums\Salutation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            // Optional: leaving it off is a real choice, because "Beste Daan"
            // wants no salutation at all.
            'salutation' => ['nullable', Rule::enum(Salutation::class)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'billing_address' => ['required', 'string', 'max:2000'],
            'country' => ['required', 'string', Rule::in(array_keys(Config::array('xoloff.countries')))],
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
            'salutation' => __('salutation'),
            'first_name' => __('first name'),
            'last_name' => __('last name'),
            'email' => __('email address'),
            'billing_address' => __('billing address'),
            'country' => __('country'),
        ];
    }
}
