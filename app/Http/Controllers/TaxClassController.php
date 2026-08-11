<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaxClassRequest;
use App\Models\TaxClass;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaxClassController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('tax-classes/Index', [
            'taxClasses' => TaxClass::query()
                ->withCount('products')
                ->orderByDesc('percentage')
                ->get(['id', 'name', 'percentage']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tax-classes/Create');
    }

    public function store(TaxClassRequest $request): RedirectResponse
    {
        TaxClass::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax class created.')]);

        return to_route('tax-classes.index');
    }

    public function edit(TaxClass $taxClass): Response
    {
        return Inertia::render('tax-classes/Edit', [
            'taxClass' => $taxClass,
        ]);
    }

    public function update(TaxClassRequest $request, TaxClass $taxClass): RedirectResponse
    {
        $taxClass->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax class updated.')]);

        return to_route('tax-classes.index');
    }

    /**
     * Refused while any product still references this tax class - the foreign
     * key restricts deletion, so we check first and explain rather than 500.
     */
    public function destroy(TaxClass $taxClass): RedirectResponse
    {
        if ($taxClass->products()->exists()) {
            return back()->withErrors([
                'taxClass' => __('This tax class is still used by one or more products.'),
            ]);
        }

        $taxClass->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax class deleted.')]);

        return to_route('tax-classes.index');
    }
}
