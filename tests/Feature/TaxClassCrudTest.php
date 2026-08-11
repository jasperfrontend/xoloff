<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaxClassCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_tax_classes()
    {
        TaxClass::factory()->create(['name' => 'Standard 21%', 'percentage' => 21.00]);

        $this->actingAs(User::factory()->create())
            ->get(route('tax-classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tax-classes/Index')
                ->has('taxClasses', 1)
                ->where('taxClasses.0.name', 'Standard 21%'),
            );
    }

    public function test_tax_class_can_be_created()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('tax-classes.store'), [
                'name' => 'Reduced 9%',
                'percentage' => 9.00,
            ])
            ->assertRedirect(route('tax-classes.index'));

        $this->assertDatabaseHas('tax_classes', [
            'name' => 'Reduced 9%',
            'percentage' => 9.00,
        ]);
    }

    /**
     * Zero is a legitimate rate — reverse charge for non-EU customers depends
     * on it, so it must never be rejected as "empty".
     */
    public function test_a_zero_percent_rate_is_accepted()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('tax-classes.store'), [
                'name' => 'Zero-rated / reverse charge',
                'percentage' => 0,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tax-classes.index'));

        $this->assertDatabaseHas('tax_classes', [
            'name' => 'Zero-rated / reverse charge',
            'percentage' => 0.00,
        ]);
    }

    public function test_percentage_must_be_within_range()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('tax-classes.store'), ['name' => 'Silly', 'percentage' => 101])
            ->assertSessionHasErrors('percentage');

        $this->actingAs(User::factory()->create())
            ->post(route('tax-classes.store'), ['name' => 'Negative', 'percentage' => -1])
            ->assertSessionHasErrors('percentage');

        $this->assertDatabaseCount('tax_classes', 0);
    }

    public function test_tax_class_can_be_updated()
    {
        $taxClass = TaxClass::factory()->create(['percentage' => 21.00]);

        $this->actingAs(User::factory()->create())
            ->put(route('tax-classes.update', $taxClass), [
                'name' => 'Standard 22%',
                'percentage' => 22.00,
            ])
            ->assertRedirect(route('tax-classes.index'));

        $this->assertDatabaseHas('tax_classes', [
            'id' => $taxClass->id,
            'percentage' => 22.00,
        ]);
    }

    public function test_unused_tax_class_can_be_deleted()
    {
        $taxClass = TaxClass::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('tax-classes.destroy', $taxClass))
            ->assertRedirect(route('tax-classes.index'));

        $this->assertDatabaseMissing('tax_classes', ['id' => $taxClass->id]);
    }

    /**
     * The foreign key restricts deletion; the controller must explain that
     * rather than let a database exception surface as a 500.
     */
    public function test_tax_class_in_use_cannot_be_deleted()
    {
        $taxClass = TaxClass::factory()->create();
        Product::factory()->create(['tax_class_id' => $taxClass->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('tax-classes.destroy', $taxClass))
            ->assertSessionHasErrors('taxClass');

        $this->assertDatabaseHas('tax_classes', ['id' => $taxClass->id]);
    }
}
