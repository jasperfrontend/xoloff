<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('products/Index', [
            'products' => Product::query()
                ->with(['taxClass:id,name,percentage', 'category:id,name'])
                ->withCount('specs')
                ->orderBy('name')
                ->get(['id', 'name', 'price_ex_vat', 'tax_class_id', 'category_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/Create', $this->formOptions());
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $product = Product::create($data);
            $this->syncSpecs($product, $data['specs'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('products.index');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('products/Edit', [
            'product' => $product->load('specs:id,product_id,key,value'),
            ...$this->formOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $product) {
            $product->update($data);
            $this->syncSpecs($product, $data['specs'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('products.index');
    }

    /**
     * Specs are replaced wholesale rather than diffed - the list is short and
     * order matters, so rewriting it is simpler and avoids stale rows.
     *
     * @param  array<int, array{key: string, value: string}>  $specs
     */
    private function syncSpecs(Product $product, array $specs): void
    {
        $product->specs()->delete();

        foreach ($specs as $spec) {
            $product->specs()->create([
                'key' => $spec['key'],
                'value' => $spec['value'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'taxClasses' => TaxClass::query()
                ->orderByDesc('percentage')
                ->get(['id', 'name', 'percentage']),
            'categories' => ProductCategory::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
