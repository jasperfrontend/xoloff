<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSpec;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_products_with_relations()
    {
        $product = Product::factory()->create(['name' => 'Managed WordPress']);
        ProductSpec::factory()->count(3)->create(['product_id' => $product->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Index')
                ->has('products', 1)
                ->where('products.0.name', 'Managed WordPress')
                ->where('products.0.specs_count', 3)
                ->has('products.0.tax_class')
                ->has('products.0.category'),
            );
    }

    public function test_create_page_offers_tax_classes_and_categories()
    {
        TaxClass::factory()->create();
        ProductCategory::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('products.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Create')
                ->has('taxClasses', 1)
                ->has('categories', 1),
            );
    }

    public function test_product_can_be_created_with_specs()
    {
        $taxClass = TaxClass::factory()->create();
        $category = ProductCategory::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), [
                'name' => 'Managed WordPress',
                'price_ex_vat' => '49.95',
                'tax_class_id' => $taxClass->id,
                'category_id' => $category->id,
                'specs' => [
                    ['key' => 'Billing period', 'value' => 'Monthly'],
                    ['key' => 'Contract duration', 'value' => '12 months'],
                ],
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Managed WordPress',
            'price_ex_vat' => '49.95',
            'tax_class_id' => $taxClass->id,
        ]);

        $this->assertDatabaseHas('product_specs', [
            'key' => 'Billing period',
            'value' => 'Monthly',
        ]);
        $this->assertDatabaseCount('product_specs', 2);
    }

    public function test_product_can_be_created_without_a_category()
    {
        $taxClass = TaxClass::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), [
                'name' => 'One-off migration',
                'price_ex_vat' => '250.00',
                'tax_class_id' => $taxClass->id,
                'category_id' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'One-off migration',
            'category_id' => null,
        ]);
    }

    public function test_product_creation_validates_its_references()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('products.store'), [
                'name' => 'Broken',
                'price_ex_vat' => '10.00',
                'tax_class_id' => 999999,
                'category_id' => 999999,
            ])
            ->assertSessionHasErrors(['tax_class_id', 'category_id']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_edit_page_includes_existing_specs()
    {
        $product = Product::factory()->create();
        ProductSpec::factory()->create([
            'product_id' => $product->id,
            'key' => 'Storage',
            'value' => '50 GB',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/Edit')
                ->where('product.id', $product->id)
                ->has('product.specs', 1)
                ->where('product.specs.0.key', 'Storage'),
            );
    }

    /**
     * Specs are replaced wholesale on save, so a shorter list must actually
     * shrink the stored rows rather than leaving orphans behind.
     */
    public function test_updating_a_product_replaces_its_specs()
    {
        $product = Product::factory()->create();
        ProductSpec::factory()->count(3)->create(['product_id' => $product->id]);

        $this->actingAs(User::factory()->create())
            ->put(route('products.update', $product), [
                'name' => $product->name,
                'price_ex_vat' => '99.00',
                'tax_class_id' => $product->tax_class_id,
                'category_id' => $product->category_id,
                'specs' => [
                    ['key' => 'Bandwidth', 'value' => 'Unmetered'],
                ],
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseCount('product_specs', 1);
        $this->assertDatabaseHas('product_specs', [
            'product_id' => $product->id,
            'key' => 'Bandwidth',
        ]);
    }

    public function test_updating_a_product_with_no_specs_clears_them()
    {
        $product = Product::factory()->create();
        ProductSpec::factory()->count(2)->create(['product_id' => $product->id]);

        $this->actingAs(User::factory()->create())
            ->put(route('products.update', $product), [
                'name' => $product->name,
                'price_ex_vat' => '99.00',
                'tax_class_id' => $product->tax_class_id,
                'category_id' => $product->category_id,
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseCount('product_specs', 0);
    }

    public function test_deleting_a_product_cascades_to_its_specs()
    {
        $product = Product::factory()->create();
        ProductSpec::factory()->count(2)->create(['product_id' => $product->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseCount('product_specs', 0);
    }
}
