<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('product-categories/Index', [
            'categories' => ProductCategory::query()
                ->withCount('products')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('product-categories/Create');
    }

    public function store(ProductCategoryRequest $request): RedirectResponse
    {
        ProductCategory::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('product-categories.index');
    }

    public function edit(ProductCategory $productCategory): Response
    {
        return Inertia::render('product-categories/Edit', [
            'category' => $productCategory,
        ]);
    }

    public function update(ProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('product-categories.index');
    }

    /**
     * Products in this category are kept - their category_id is nulled by the
     * foreign key, so deleting a tag never destroys catalog data.
     */
    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('product-categories.index');
    }
}
