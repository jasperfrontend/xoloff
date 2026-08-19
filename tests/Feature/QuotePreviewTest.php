<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\TaxClass;
use App\Models\User;
use App\Support\Quotes\QuoteCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The builder shows a running total by asking the real engine, rather than
 * reimplementing SPEC §5 in the browser. These tests guard that the preview
 * agrees with a saved quote and never writes anything.
 */
class QuotePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_totals_for_unsaved_content()
    {
        $standard = TaxClass::factory()->create(['percentage' => 21.00]);
        $reduced = TaxClass::factory()->create(['percentage' => 9.00]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('quotes.preview'), [
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 10,
                'line_items' => [
                    [
                        'name' => 'Standard rated',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 100.00,
                        'tax_class_id' => $standard->id,
                    ],
                    [
                        'name' => 'Reduced rated',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 100.00,
                        'tax_class_id' => $reduced->id,
                    ],
                ],
            ])
            ->assertOk();

        $response->assertJsonPath('subtotal', '180.00');
        $response->assertJsonPath('vatTotal', '27.00');
        $response->assertJsonPath('total', '207.00');
        $response->assertJsonPath('lines.0.net', '90.00');
        $response->assertJsonPath('taxClassTotals.0.vat', '18.90');
        $response->assertJsonPath('taxClassTotals.1.vat', '8.10');
    }

    public function test_it_saves_nothing()
    {
        $taxClass = TaxClass::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('quotes.preview'), [
                'line_items' => [
                    [
                        'name' => 'Line',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 100.00,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseCount('quotes', 0);
        $this->assertDatabaseCount('quote_versions', 0);
        $this->assertDatabaseCount('quote_line_items', 0);
    }

    public function test_it_agrees_with_the_same_content_once_saved()
    {
        $taxClass = TaxClass::factory()->create(['percentage' => 21.00]);

        $lineItems = [
            [
                'name' => 'Awkward line',
                'quantity' => 3,
                'unit_price_ex_vat' => 19.99,
                'tax_class_id' => $taxClass->id,
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 7.77,
            ],
            [
                'name' => 'Another',
                'quantity' => 1,
                'unit_price_ex_vat' => 0.07,
                'tax_class_id' => $taxClass->id,
            ],
        ];

        $user = User::factory()->create();

        $previewed = $this->actingAs($user)
            ->postJson(route('quotes.preview'), [
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 33.33,
                'line_items' => $lineItems,
            ])
            ->assertOk()
            ->json('total');

        $this->actingAs($user)
            ->post(route('quotes.store'), [
                'customer_id' => Customer::factory()->create()->id,
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 33.33,
                'line_items' => $lineItems,
            ])
            ->assertRedirect();

        $saved = (new QuoteCalculator)->calculate(Quote::sole()->currentVersion)->total;

        // The whole point of previewing on the server: the number shown while
        // editing is the number that gets saved.
        $this->assertSame($previewed, $saved);
    }

    public function test_it_does_not_need_a_customer()
    {
        $taxClass = TaxClass::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('quotes.preview'), [
                'line_items' => [
                    [
                        'name' => 'Line',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 10.00,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('total', '12.10');
    }

    public function test_it_rejects_a_percentage_discount_over_one_hundred()
    {
        $taxClass = TaxClass::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('quotes.preview'), [
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 120,
                'line_items' => [
                    [
                        'name' => 'Line',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 10.00,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_value');
    }

    public function test_it_names_fields_the_way_the_builder_does()
    {
        $taxClass = TaxClass::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('quotes.preview'), [
                'discount_type' => DiscountType::Percentage->value,
                'line_items' => [
                    [
                        'name' => 'Line',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 10.00,
                        'tax_class_id' => $taxClass->id,
                        'discount_type' => DiscountType::Percentage->value,
                    ],
                ],
            ])
            ->assertUnprocessable();

        // These messages are shown verbatim next to the inputs, so they must
        // not leak the shape of the payload.
        $errors = $response->json('errors');

        $this->assertSame(
            'Enter an amount for the quote discount, or set it back to no discount.',
            $errors['discount_value'][0],
        );

        $this->assertSame(
            'Enter an amount for this line discount, or set it back to no discount.',
            $errors['line_items.0.discount_value'][0],
        );
    }

    public function test_it_never_shows_a_raw_field_path_to_a_person()
    {
        $taxClass = TaxClass::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('quotes.preview'), [
                'line_items' => [
                    [
                        'name' => '',
                        'quantity' => 'not a number',
                        'unit_price_ex_vat' => -5,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertUnprocessable();

        /** @var array<string, array<int, string>> $errors */
        $errors = $response->json('errors');

        foreach ($errors as $messages) {
            foreach ($messages as $message) {
                $this->assertStringNotContainsString('line_items.', $message);
                $this->assertStringNotContainsString('_', $message);
            }
        }
    }

    public function test_an_empty_builder_previews_as_zero()
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('quotes.preview'), ['line_items' => []])
            ->assertOk()
            ->assertJsonPath('total', '0.00');
    }
}
