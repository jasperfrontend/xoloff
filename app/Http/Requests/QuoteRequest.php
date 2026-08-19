<?php

namespace App\Http\Requests;

use App\Concerns\QuoteContentValidationRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    use QuoteContentValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            ...$this->quoteContentRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->rejectPercentagesOverOneHundred($validator));
    }
}
