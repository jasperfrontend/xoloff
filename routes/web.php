<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TaxClassController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Reference data (SPEC §4). No `show` routes — the index rows link
    // straight to edit, which is the only thing either user ever wants.
    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::resource('product-categories', ProductCategoryController::class)->except(['show']);
    Route::resource('tax-classes', TaxClassController::class)->except(['show']);
    Route::resource('products', ProductController::class)->except(['show']);
});

require __DIR__.'/settings.php';
