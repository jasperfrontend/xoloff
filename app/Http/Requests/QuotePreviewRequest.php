<?php

namespace App\Http\Requests;

use App\Concerns\QuoteContentValidationRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The same content as a save, minus the customer. Totals do not depend on who
 * the quote is for, and the builder shows them while lines are still being
 * added, which is usually before a customer has been picked.
 */
class QuotePreviewRequest extends FormRequest
{
    use QuoteContentValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->quoteContentRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->quoteContentAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->quoteContentMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->rejectPercentagesOverOneHundred($validator));
    }
}
