<?php

namespace Tests\Feature;

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
                'contact_person' => 'Jan Jansen',
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

    public function test_customer_creation_validates_required_fields()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('customers.store'), [])
            ->assertSessionHasErrors([
                'company_name',
                'contact_person',
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
                'contact_person' => 'Jan Jansen',
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
                'contact_person' => 'Piet Pietersen',
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
