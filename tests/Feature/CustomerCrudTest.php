<?php

namespace Tests\Feature;

use App\Enums\Salutation;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_customers()
    {
        $customer = Customer::factory()->create(['company_name' => 'Acme BV']);

        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/Index')
                ->has('customers', 1)
                ->where('customers.0.company_name', 'Acme BV')
                ->where('customers.0.id', $customer->id),
            );
    }

    public function test_create_page_renders_with_country_options()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/Create')
                ->where('countries.NL', 'Netherlands')
                ->where('countries.US', 'United States'),
            );
    }

    public function test_customer_can_be_created()
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), [
                'company_name' => 'Acme BV',
                'first_name' => 'Jan',
                'last_name' => 'Jansen',
                'email' => 'jan@acme.test',
                'billing_address' => "Keizersgracht 1\n1015 CD Amsterdam",
                'country' => 'NL',
            ]);

        $response->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'company_name' => 'Acme BV',
            'email' => 'jan@acme.test',
            'country' => 'NL',
        ]);
    }

    /**
     * A quote text greets a person by name, so the parts are stored
     * separately. "Beste Daan Daansen" is not how anyone writes.
     */
    public function test_a_contact_is_stored_as_salutation_first_name_and_last_name()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), [
                'company_name' => 'Acme BV',
                'salutation' => Salutation::Mevrouw->value,
                'first_name' => 'Anna',
                'last_name' => 'de Vries',
                'email' => 'anna@acme.test',
                'billing_address' => 'Keizersgracht 1',
                'country' => 'NL',
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::sole();

        $this->assertSame(Salutation::Mevrouw, $customer->salutation);
        $this->assertSame('Anna', $customer->first_name);
        $this->assertSame('de Vries', $customer->last_name);
    }

    /**
     * Leaving it off is a real choice rather than a missing value: "Beste
     * Anna" wants no salutation at all.
     */
    public function test_a_contact_needs_no_salutation()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), [
                'company_name' => 'Acme BV',
                'salutation' => null,
                'first_name' => 'Anna',
                'last_name' => 'de Vries',
                'email' => 'anna@acme.test',
                'billing_address' => 'Keizersgracht 1',
                'country' => 'NL',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(Customer::sole()->salutation);
    }

    public function test_a_salutation_outside_the_two_is_refused()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), [
                'company_name' => 'Acme BV',
                'salutation' => 'kapitein',
                'first_name' => 'Anna',
                'last_name' => 'de Vries',
                'email' => 'anna@acme.test',
                'billing_address' => 'Keizersgracht 1',
                'country' => 'NL',
            ])
            ->assertSessionHasErrors('salutation');
    }

    /**
     * Derived rather than stored, so a list and a form can never disagree
     * about someone's name. The salutation is left out: this reads as a name
     * on an envelope, and "heer Jan Jansen" is not one.
     */
    public function test_the_whole_name_is_derived_from_its_parts()
    {
        $customer = Customer::factory()->create([
            'salutation' => Salutation::Heer,
            'first_name' => 'Jan',
            'last_name' => 'Jansen',
        ]);

        $this->assertSame('Jan Jansen', $customer->contact_person);
    }

    public function test_the_list_shows_the_whole_name()
    {
        Customer::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen']);

        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.0.contact_person', 'Jan Jansen'),
            );
    }

    public function test_the_form_offers_the_salutations_it_accepts()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customers.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('salutations.heer', 'Mr (heer)')
                ->where('salutations.mevrouw', 'Ms (mevrouw)')
                ->has('salutations', 2),
            );
    }

    public function test_customer_creation_validates_required_fields()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), [])
            ->assertSessionHasErrors([
                'company_name',
                'first_name',
                'last_name',
                'email',
                'billing_address',
                'country',
            ]);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_creation_rejects_an_unknown_country()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), [
                'company_name' => 'Acme BV',
                'first_name' => 'Jan',
                'last_name' => 'Jansen',
                'email' => 'jan@acme.test',
                'billing_address' => 'Keizersgracht 1',
                'country' => 'XX',
            ])
            ->assertSessionHasErrors('country');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_edit_page_renders_the_customer()
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/Edit')
                ->where('customer.id', $customer->id)
                ->has('countries'),
            );
    }

    public function test_customer_can_be_updated()
    {
        $customer = Customer::factory()->create(['country' => 'NL']);

        $this->actingAs(User::factory()->create())
            ->put(route('customers.update', $customer), [
                'company_name' => 'Renamed BV',
                'first_name' => 'Piet',
                'last_name' => 'Pietersen',
                'email' => 'piet@renamed.test',
                'billing_address' => 'Somewhere 2',
                'country' => 'US',
            ])
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'company_name' => 'Renamed BV',
            'country' => 'US',
        ]);
    }

    public function test_customer_can_be_deleted()
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
