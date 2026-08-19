<?php

namespace App\Concerns;

use App\Enums\DiscountType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * The content of a quote version, shared by the save and the live preview so
 * the two can never drift apart on what they accept.
 */
trait QuoteContentValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function quoteContentRules(): array
    {
        return [
            'discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'required_with:discount_type'],
            'rounding_override' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            // A quote with no lines is a legitimate draft, so the list may be
            // empty. The calculation engine totals it at zero.
            'line_items' => ['array'],
            'line_items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'line_items.*.name' => ['required', 'string', 'max:255'],
            'line_items.*.specs' => ['nullable', 'array'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'line_items.*.unit_price_ex_vat' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'line_items.*.tax_class_id' => ['required', 'integer', 'exists:tax_classes,id'],
            'line_items.*.discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'line_items.*.discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'required_with:line_items.*.discount_type'],
        ];
    }

    /**
     * A percentage over 100 would discount past zero, so it is rejected rather
     * than clamped. Fixed amounts are left to the engine, which caps them at
     * whatever they apply to. Checking those here would mean reimplementing the
     * line and subtotal arithmetic in a second place.
     */
    protected function rejectPercentagesOverOneHundred(Validator $validator): void
    {
        if ($this->exceedsOneHundred($this->input('discount_type'), $this->input('discount_value'))) {
            $validator->errors()->add('discount_value', __('A percentage discount cannot exceed 100%.'));
        }

        /** @var array<int, array<string, mixed>> $lineItems */
        $lineItems = (array) $this->input('line_items', []);

        foreach ($lineItems as $index => $lineItem) {
            if ($this->exceedsOneHundred($lineItem['discount_type'] ?? null, $lineItem['discount_value'] ?? null)) {
                $validator->errors()->add(
                    "line_items.{$index}.discount_value",
                    __('A percentage discount cannot exceed 100%.'),
                );
            }
        }
    }

    private function exceedsOneHundred(mixed $type, mixed $value): bool
    {
        return $type === DiscountType::Percentage->value
            && is_numeric($value)
            && (float) $value > 100;
    }
}
