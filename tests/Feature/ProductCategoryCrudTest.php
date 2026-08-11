<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductCategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_categories_with_product_counts()
    {
        $category = ProductCategory::factory()->create(['name' => 'Hosting']);
        Product::factory()->count(2)->create(['category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('product-categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('product-categories/Index')
                ->has('categories', 1)
                ->where('categories.0.name', 'Hosting')
                ->where('categories.0.products_count', 2),
            );
    }

    public function test_category_can_be_created()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('product-categories.store'), ['name' => 'Hosting'])
            ->assertRedirect(route('product-categories.index'));

        $this->assertDatabaseHas('product_categories', ['name' => 'Hosting']);
    }

    public function test_category_name_must_be_unique()
    {
        ProductCategory::factory()->create(['name' => 'Hosting']);

        $this->actingAs(User::factory()->create())
            ->post(route('product-categories.store'), ['name' => 'Hosting'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('product_categories', 1);
    }

    public function test_category_can_keep_its_own_name_when_updated()
    {
        $category = ProductCategory::factory()->create(['name' => 'Hosting']);

        $this->actingAs(User::factory()->create())
            ->put(route('product-categories.update', $category), ['name' => 'Hosting'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('product-categories.index'));
    }

    public function test_category_can_be_renamed()
    {
        $category = ProductCategory::factory()->create(['name' => 'Hosting']);

        $this->actingAs(User::factory()->create())
            ->put(route('product-categories.update', $category), ['name' => 'Managed hosting'])
            ->assertRedirect(route('product-categories.index'));

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'name' => 'Managed hosting',
        ]);
    }

    /**
     * Deleting a tag must never destroy catalog data - the foreign key nulls
     * the reference instead of cascading.
     */
    public function test_deleting_a_category_keeps_its_products_but_uncategorises_them()
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs(User::factory()->create())
            ->delete(route('product-categories.destroy', $category))
            ->assertRedirect(route('product-categories.index'));

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => null,
        ]);
    }
}
