<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLogEntry;
use App\Models\Customer;
use App\Models\PremadeText;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * SPEC §3 asks for one log covering CRUD and event alike, browsable and
 * filterable by quote, by date range and by who caused the entry. SPEC §6 adds
 * that every CRUD, version and PDF action has to be visible in it.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_reference_data_is_recorded()
    {
        $this->actingAs($user = User::factory()->create())
            ->post(route('customers.store'), [
                'company_name' => 'Acme BV',
                'contact_person' => 'Sam Jansen',
                'email' => 'sam@acme.nl',
                'billing_address' => 'Dorpsstraat 1',
                'country' => 'NL',
            ])
            ->assertRedirect();

        $entry = AuditLogEntry::sole();

        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame('customer', $entry->entity_type);
        $this->assertSame(AuditAction::Created, $entry->action);
        $this->assertSame('Acme BV', $entry->payload['label']);
        $this->assertSame('Acme BV', $entry->payload['attributes']['company_name']);
    }

    public function test_an_update_records_what_changed_and_nothing_else()
    {
        $taxClass = TaxClass::factory()->create(['name' => 'Standard 21%', 'percentage' => 21.00]);

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->put(route('tax-classes.update', $taxClass), [
                'name' => 'Standaard 21%',
                'percentage' => 21.00,
            ])
            ->assertRedirect();

        $changes = AuditLogEntry::sole()->payload['changes'];

        $this->assertSame(['name'], array_keys($changes));
        $this->assertSame('Standard 21%', $changes['name']['from']);
        $this->assertSame('Standaard 21%', $changes['name']['to']);
    }

    /**
     * Reopening a screen and saving it untouched is not an event, and a log
     * full of them is a log nobody reads. Eloquent does most of this by not
     * firing at all when nothing is dirty.
     */
    public function test_a_save_that_changed_nothing_is_not_recorded()
    {
        $category = ProductCategory::factory()->create(['name' => 'Hosting']);

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->put(route('product-categories.update', $category), ['name' => 'Hosting'])
            ->assertRedirect();

        $this->assertDatabaseCount('audit_log', 0);
    }

    /**
     * The case Eloquent does not cover: a write that really did happen, but
     * touched nothing the log reports on. A timestamp is that today; a hidden
     * column such as the magic link token in M4 would be that tomorrow. Either
     * way, an entry saying "changed: nothing" is noise.
     */
    public function test_a_write_that_touched_nothing_worth_reporting_is_not_recorded()
    {
        $category = ProductCategory::factory()->create(['name' => 'Hosting']);

        AuditLogEntry::query()->delete();

        // A second later, or the new timestamp equals the old one and the save
        // is skipped before any of this is reached.
        $this->travel(1)->seconds();

        $category->touch();

        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_a_deletion_keeps_the_row_it_removed()
    {
        $category = ProductCategory::factory()->create(['name' => 'Hosting']);

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->delete(route('product-categories.destroy', $category))
            ->assertRedirect();

        $entry = AuditLogEntry::sole();

        $this->assertSame(AuditAction::Deleted, $entry->action);
        $this->assertSame('Hosting', $entry->payload['label']);
        $this->assertSame('Hosting', $entry->payload['attributes']['name']);
    }

    /**
     * A product and its specs are one thing to the person editing them. Changing
     * only a spec leaves the product row untouched, so a log driven purely by
     * model events would miss the edit.
     */
    public function test_changing_only_a_product_spec_is_still_recorded()
    {
        $product = $this->product();

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->put(route('products.update', $product), [
                'name' => $product->name,
                'price_ex_vat' => $product->price_ex_vat,
                'tax_class_id' => $product->tax_class_id,
                'category_id' => $product->category_id,
                'specs' => [['key' => 'Billing period', 'value' => 'Yearly']],
            ])
            ->assertRedirect();

        $changes = AuditLogEntry::sole()->payload['changes'];

        $this->assertSame(['specs'], array_keys($changes));
        $this->assertSame(['Billing period' => 'Monthly'], $changes['specs']['from']);
        $this->assertSame(['Billing period' => 'Yearly'], $changes['specs']['to']);
    }

    public function test_a_product_edit_produces_one_entry_rather_than_two()
    {
        $product = $this->product();

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->put(route('products.update', $product), [
                'name' => 'Managed hosting XL',
                'price_ex_vat' => $product->price_ex_vat,
                'tax_class_id' => $product->tax_class_id,
                'category_id' => $product->category_id,
                'specs' => [['key' => 'Billing period', 'value' => 'Yearly']],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('audit_log', 1);
        $this->assertSame(
            ['name', 'specs'],
            array_keys(AuditLogEntry::sole()->payload['changes']),
        );
    }

    public function test_building_a_quote_records_the_quote_and_its_version()
    {
        // Prepared first, because creating the customer and the tax class it
        // refers to is itself recorded.
        $content = $this->quoteContent();

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), $content)
            ->assertRedirect();

        $quote = Quote::sole();
        $entries = AuditLogEntry::query()->orderBy('id')->get();

        $this->assertSame(['quote', 'quote_version'], $entries->pluck('entity_type')->all());
        $this->assertSame('Quote 1 version 1', $entries[1]->payload['label']);
        $this->assertSame($quote->id, $entries[1]->payload['quote_id']);
    }

    /**
     * The lines are most of what a version is, and none of them exist when the
     * version row itself is written.
     */
    public function test_a_version_entry_carries_its_lines()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('quotes.store'), $this->quoteContent())
            ->assertRedirect();

        $lineItems = AuditLogEntry::query()
            ->where('entity_type', 'quote_version')
            ->sole()
            ->payload['attributes']['line_items'];

        $this->assertCount(1, $lineItems);
        $this->assertSame('Managed hosting', $lineItems[0]['name']);
    }

    public function test_editing_only_the_lines_of_a_quote_is_recorded()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quotes.store'), $this->quoteContent())->assertRedirect();

        $quote = Quote::sole();

        AuditLogEntry::query()->delete();

        $content = $this->quoteContent();
        $content['line_items'][0]['quantity'] = 5;

        $this->actingAs($user)
            ->put(route('quotes.update', $quote), $content)
            ->assertRedirect();

        $entry = AuditLogEntry::query()->where('entity_type', 'quote_version')->sole();

        $this->assertSame(AuditAction::Updated, $entry->action);
        $this->assertSame(['line_items'], array_keys($entry->payload['changes']));
    }

    public function test_deleting_a_version_is_recorded()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quotes.store'), $this->quoteContent())->assertRedirect();

        $quote = Quote::sole();

        $this->actingAs($user)
            ->post(route('quotes.versions.store', $quote), $this->quoteContent())
            ->assertRedirect();

        $first = $quote->versions()->where('version_number', 1)->sole();

        AuditLogEntry::query()->delete();

        $this->actingAs($user)
            ->delete(route('quotes.versions.destroy', [$quote, $first]))
            ->assertRedirect();

        $entry = AuditLogEntry::sole();

        $this->assertSame(AuditAction::Deleted, $entry->action);
        $this->assertSame('quote_version', $entry->entity_type);
        $this->assertSame($quote->id, $entry->payload['quote_id']);
    }

    public function test_editing_the_quote_texts_is_recorded()
    {
        PremadeText::factory()->footer()->create(['content' => '<p>Oud</p>']);

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->put(route('premade-texts.update'), ['intro' => '', 'footer' => '<p>Nieuw</p>'])
            ->assertSessionHasNoErrors();

        $entry = AuditLogEntry::query()->where('entity_type', 'premade_text')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertSame('Footer text', $entry->payload['label']);
    }

    public function test_uploading_a_logo_is_recorded()
    {
        Storage::fake('public');

        AuditLogEntry::query()->delete();

        $this->actingAs(User::factory()->create())
            ->post(route('app-settings.update'), [
                'logo' => UploadedFile::fake()->image('xolution.png'),
            ])
            ->assertSessionHasNoErrors();

        $entry = AuditLogEntry::sole();

        $this->assertSame('app_settings', $entry->entity_type);
        $this->assertSame('Application settings', $entry->payload['label']);
    }

    /**
     * Names are written down in the morph map rather than derived from class
     * names, so an entry written today still reads the same after a model is
     * renamed. Storing a fully qualified class name would be the failure.
     */
    public function test_entities_are_named_readably_rather_than_by_class()
    {
        Customer::factory()->create();

        $this->assertSame('customer', AuditLogEntry::sole()->entity_type);
    }

    public function test_the_log_is_browsable()
    {
        Customer::factory()->count(3)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit-log/Index')
                ->has('entries.data', 3)
                ->where('entries.total', 3),
            );
    }

    public function test_it_filters_by_quote_including_that_quotes_versions()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('quotes.store'), $this->quoteContent())->assertRedirect();
        $this->actingAs($user)->post(route('quotes.store'), $this->quoteContent())->assertRedirect();

        $first = Quote::query()->orderBy('id')->first();

        $this->actingAs($user)
            ->get(route('audit-log.index', ['quote_id' => $first->id]))
            ->assertInertia(fn (Assert $page) => $page
                // The quote and its version, and nothing from the other quote.
                ->has('entries.data', 2)
                ->where('entries.data.0.quote_id', $first->id)
                ->where('entries.data.1.quote_id', $first->id),
            );
    }

    public function test_it_filters_by_who_caused_the_entry()
    {
        $jasper = User::factory()->create(['name' => 'Jasper']);
        $stephan = User::factory()->create(['name' => 'Stephan']);

        $this->actingAs($jasper)->post(route('product-categories.store'), ['name' => 'Hosting']);
        $this->actingAs($stephan)->post(route('product-categories.store'), ['name' => 'Development']);

        $this->actingAs($jasper)
            ->get(route('audit-log.index', ['user_id' => $stephan->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.user_name', 'Stephan'),
            );
    }

    public function test_it_filters_by_date_range_inclusively()
    {
        $user = User::factory()->create();

        $old = Customer::factory()->create();
        AuditLogEntry::query()->update(['created_at' => '2026-08-01 10:00:00']);

        Customer::factory()->create();
        AuditLogEntry::query()->latest('id')->first()
            ->update(['created_at' => '2026-08-20 10:00:00']);

        // The day named as the boundary counts: filtering "to the first" must
        // include what happened that day, not stop at midnight before it.
        $this->actingAs($user)
            ->get(route('audit-log.index', ['from' => '2026-08-01', 'to' => '2026-08-01']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.entity_id', $old->id),
            );
    }

    public function test_it_filters_by_action()
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        $this->actingAs(User::factory()->create())
            ->get(route('audit-log.index', ['action' => AuditAction::Deleted->value]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.action', 'deleted'),
            );
    }

    /**
     * Someone typing in the address bar is browsing, not attacking. A nonsense
     * filter shows everything rather than throwing an error at them.
     */
    public function test_a_nonsense_filter_is_ignored_rather_than_refused()
    {
        Customer::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('audit-log.index', ['action' => 'exploded', 'from' => 'yesterday', 'quote_id' => 'abc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));
    }

    /**
     * A seeder or a console command has nobody behind it (SPEC §3).
     */
    public function test_an_entry_with_nobody_behind_it_is_allowed()
    {
        Customer::factory()->create();

        $this->assertNull(AuditLogEntry::sole()->user_id);
    }

    /**
     * Deleting a user must not erase the record of what they did.
     */
    public function test_an_entry_outlives_the_person_who_caused_it()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('product-categories.store'), ['name' => 'Hosting']);

        $user->delete();

        $entry = AuditLogEntry::sole();

        $this->assertNull($entry->fresh()->user_id);
        $this->assertSame('Hosting', $entry->payload['label']);
    }

    public function test_a_guest_cannot_read_the_log()
    {
        $this->get(route('audit-log.index'))->assertRedirect(route('login'));
    }

    private function product(): Product
    {
        $product = Product::factory()->create(['name' => 'Managed hosting']);

        $product->specs()->create(['key' => 'Billing period', 'value' => 'Monthly']);

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteContent(): array
    {
        return [
            'customer_id' => Customer::firstOr(fn () => Customer::factory()->create())->id,
            'line_items' => [
                [
                    'name' => 'Managed hosting',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 90.00,
                    'tax_class_id' => TaxClass::firstOr(fn () => TaxClass::factory()->create())->id,
                ],
            ],
        ];
    }
}
