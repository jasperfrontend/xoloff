<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuoteCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_quotes_with_their_current_total()
    {
        $version = QuoteVersion::factory()->create();

        QuoteLineItem::factory()->create([
            'quote_version_id' => $version->id,
            'quantity' => 2,
            'unit_price_ex_vat' => 100.00,
            'tax_class_id' => TaxClass::factory()->create(['percentage' => 21.00]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/Index')
                ->has('quotes', 1)
                ->where('quotes.0.total', '242.00')
                ->where('quotes.0.version_number', 1)
                ->where('quotes.0.line_count', 1),
            );
    }

    /**
     * SPEC §3 names draft the implicit default. It is a real stored value
     * rather than an absence, so nothing downstream has to treat null as if
     * it meant something.
     */
    public function test_a_new_quote_starts_as_a_draft()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), [
                'customer_id' => Customer::factory()->create()->id,
                'line_items' => [],
            ]);

        $this->assertSame(QuoteStatus::Draft, Quote::sole()->status);
    }

    public function test_the_index_shows_where_each_quote_stands()
    {
        QuoteVersion::factory()->for(Quote::factory()->sent())->create();

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('quotes.0.status', 'sent')
                ->where('quotes.0.status_label', 'Sent'),
            );
    }

    public function test_the_edit_page_shows_where_the_quote_stands()
    {
        $quote = Quote::factory()->sent()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.edit', $quote))
            ->assertInertia(fn (Assert $page) => $page
                ->where('quote.status', 'sent')
                ->where('quote.status_label', 'Sent'),
            );
    }

    /**
     * Status moves through the actions that cause it, never through a form
     * post, so a request that names one is ignored rather than obeyed.
     */
    public function test_a_request_cannot_nominate_a_status()
    {
        $quote = Quote::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('quotes.update', $quote), [
                'customer_id' => $quote->customer_id,
                'status' => QuoteStatus::Approved->value,
                'line_items' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    /**
     * The request above is refused twice over, and this is the half that
     * holds regardless of which screen does the saving: nothing reaches
     * status through a filled array, so a controller written later that
     * passes a whole request through cannot open the hole by accident.
     */
    public function test_status_is_not_mass_assignable()
    {
        $quote = Quote::factory()->create();

        $quote->update(['status' => QuoteStatus::Approved]);

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    public function test_the_builder_offers_customers_products_and_tax_classes()
    {
        Customer::factory()->create();
        Product::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/Create')
                ->has('customers', 1)
                ->has('products', 1)
                ->has('taxClasses'),
            );
    }

    public function test_a_quote_can_be_created_with_line_items()
    {
        $customer = Customer::factory()->create();
        $taxClass = TaxClass::factory()->create(['percentage' => 21.00]);

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'line_items' => [
                    [
                        'name' => 'Managed hosting',
                        'quantity' => 12,
                        'unit_price_ex_vat' => 25.00,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $quote = Quote::sole();

        $this->assertSame($customer->id, $quote->customer_id);
        $this->assertSame(1, $quote->currentVersion->version_number);
        $this->assertDatabaseHas('quote_line_items', [
            'quote_version_id' => $quote->currentVersion->id,
            'name' => 'Managed hosting',
            'quantity' => '12.00',
        ]);
    }

    public function test_a_line_item_keeps_the_specs_copied_from_its_product()
    {
        $customer = Customer::factory()->create();
        $taxClass = TaxClass::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'line_items' => [
                    [
                        'product_id' => $product->id,
                        'name' => 'Renamed after insertion',
                        'specs' => ['Billing period' => 'Monthly'],
                        'quantity' => 1,
                        'unit_price_ex_vat' => 50.00,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $lineItem = QuoteLineItem::sole();

        $this->assertSame($product->id, $lineItem->product_id);
        $this->assertSame('Renamed after insertion', $lineItem->name);
        $this->assertSame(['Billing period' => 'Monthly'], $lineItem->specs);
    }

    public function test_saving_a_quote_overwrites_the_current_version_rather_than_adding_one()
    {
        $quote = Quote::factory()->create();
        $version = QuoteVersion::factory()->create(['quote_id' => $quote->id, 'version_number' => 1]);
        $taxClass = TaxClass::factory()->create();

        QuoteLineItem::factory()->create([
            'quote_version_id' => $version->id,
            'name' => 'Original line',
            'tax_class_id' => $taxClass->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('quotes.update', $quote), [
                'customer_id' => $quote->customer_id,
                'line_items' => [
                    [
                        'name' => 'Replacement line',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 10.00,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertRedirect(route('quotes.edit', $quote));

        $this->assertSame(1, $quote->versions()->count());
        $this->assertSame('Replacement line', QuoteLineItem::sole()->name);
    }

    public function test_save_as_new_version_adds_a_version_and_leaves_the_old_one_intact()
    {
        $quote = Quote::factory()->create();
        $version = QuoteVersion::factory()->create(['quote_id' => $quote->id, 'version_number' => 1]);
        $taxClass = TaxClass::factory()->create();

        QuoteLineItem::factory()->create([
            'quote_version_id' => $version->id,
            'name' => 'Original line',
            'tax_class_id' => $taxClass->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.versions.store', $quote), [
                'customer_id' => $quote->customer_id,
                'line_items' => [
                    [
                        'name' => 'Revised line',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 10.00,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertRedirect(route('quotes.edit', $quote));

        $this->assertSame(2, $quote->versions()->count());
        $this->assertSame(2, $quote->fresh()->currentVersion->version_number);

        // The superseded version keeps its own content.
        $this->assertSame('Original line', $version->lineItems()->sole()->name);
    }

    public function test_the_edit_page_carries_the_current_version_and_its_totals()
    {
        $quote = Quote::factory()->create();
        $version = QuoteVersion::factory()
            ->withPercentageDiscount(10.00)
            ->create(['quote_id' => $quote->id, 'version_number' => 1]);

        QuoteLineItem::factory()->create([
            'quote_version_id' => $version->id,
            'quantity' => 1,
            'unit_price_ex_vat' => 100.00,
            'tax_class_id' => TaxClass::factory()->create(['percentage' => 21.00]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('quotes.edit', $quote))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/Edit')
                ->where('quote.discount_type', DiscountType::Percentage->value)
                ->has('quote.line_items', 1)
                ->where('totals.subtotal', '90.00')
                ->where('totals.vatTotal', '18.90')
                ->where('totals.total', '108.90'),
            );
    }

    public function test_a_quote_can_be_deleted_with_its_versions_and_lines()
    {
        $quote = Quote::factory()->create();
        $version = QuoteVersion::factory()->create(['quote_id' => $quote->id]);
        QuoteLineItem::factory()->create(['quote_version_id' => $version->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('quotes.destroy', $quote))
            ->assertRedirect(route('quotes.index'));

        $this->assertDatabaseCount('quotes', 0);
        $this->assertDatabaseCount('quote_versions', 0);
        $this->assertDatabaseCount('quote_line_items', 0);
    }

    public function test_a_customer_with_quotes_cannot_be_deleted()
    {
        $quote = Quote::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('customers.destroy', $quote->customer_id));

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_a_percentage_discount_over_one_hundred_is_rejected()
    {
        $customer = Customer::factory()->create();
        $taxClass = TaxClass::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 150,
                'line_items' => [
                    [
                        'name' => 'Line',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 10.00,
                        'tax_class_id' => $taxClass->id,
                        'discount_type' => DiscountType::Percentage->value,
                        'discount_value' => 101,
                    ],
                ],
            ])
            ->assertSessionHasErrors(['discount_value', 'line_items.0.discount_value']);

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_a_fixed_discount_over_one_hundred_is_perfectly_valid()
    {
        $customer = Customer::factory()->create();
        $taxClass = TaxClass::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'discount_type' => DiscountType::Fixed->value,
                'discount_value' => 150,
                'line_items' => [
                    [
                        'name' => 'Line',
                        'quantity' => 1,
                        'unit_price_ex_vat' => 500.00,
                        'tax_class_id' => $taxClass->id,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_a_quote_requires_a_customer()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), ['line_items' => []])
            ->assertSessionHasErrors('customer_id');
    }

    public function test_a_quote_with_no_lines_is_a_legitimate_draft()
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), [
                'customer_id' => $customer->id,
                'line_items' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quotes', 1);
    }
}
