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
     * Without these, a failure reads "The line_items.0.discount_value field is
     * required when line_items.0.discount_type is present", which is the shape
     * of the payload rather than anything a person would recognise on screen.
     *
     * @return array<string, string>
     */
    protected function quoteContentAttributes(): array
    {
        return [
            'discount_type' => __('quote discount'),
            'discount_value' => __('quote discount amount'),
            'rounding_override' => __('rounding override'),
            'line_items.*.product_id' => __('product'),
            'line_items.*.name' => __('description'),
            'line_items.*.quantity' => __('quantity'),
            'line_items.*.unit_price_ex_vat' => __('unit price'),
            'line_items.*.tax_class_id' => __('tax class'),
            'line_items.*.discount_type' => __('line discount'),
            'line_items.*.discount_value' => __('line discount amount'),
        ];
    }

    /**
     * Choosing a discount type and leaving its amount empty is the single most
     * likely way to get stuck here, so it says what to do rather than restating
     * the rule that was broken.
     *
     * @return array<string, string>
     */
    protected function quoteContentMessages(): array
    {
        return [
            'discount_value.required_with' => __('Enter an amount for the quote discount, or set it back to no discount.'),
            'line_items.*.discount_value.required_with' => __('Enter an amount for this line discount, or set it back to no discount.'),
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
