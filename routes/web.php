<?php

use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PremadeTextController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuotePreviewController;
use App\Http\Controllers\QuoteVersionController;
use App\Http\Controllers\TaxClassController;
use Illuminate\Support\Facades\Route;

// Xoloff has no public front page - it is an internal tool for two people.
// The root sends you into the app, and the auth middleware bounces guests
// to the login screen from there.
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Reference data (SPEC §4). No `show` routes - the index rows link
    // straight to edit, which is the only thing either user ever wants.
    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::resource('product-categories', ProductCategoryController::class)->except(['show']);
    Route::resource('tax-classes', TaxClassController::class)->except(['show']);
    Route::resource('products', ProductController::class)->except(['show']);

    // Quote builder (SPEC §5). No `show` either: editing is the only view of a
    // quote there is until M3 adds the PDF.
    Route::resource('quotes', QuoteController::class)->except(['show']);

    // Totals for content that has not been saved, so the builder can show a
    // running total without reimplementing the calculation engine in the
    // browser.
    Route::post('quotes/preview', QuotePreviewController::class)->name('quotes.preview');

    // The explicit "Save as new version" action. Editing a quote otherwise
    // saves over the current version (SPEC §3).
    Route::post('quotes/{quote}/versions', [QuoteVersionController::class, 'store'])
        ->name('quotes.versions.store');

    // The intro and footer carried by every quote (SPEC §3). Exactly two rows,
    // fixed by key, so this is an edit screen rather than a resource.
    Route::get('premade-texts', [PremadeTextController::class, 'edit'])->name('premade-texts.edit');
    Route::put('premade-texts', [PremadeTextController::class, 'update'])->name('premade-texts.update');

    // Application-wide configuration, as opposed to the per-user screens under
    // /settings. One row, so this edits rather than creating (SPEC §3).
    Route::get('app-settings', [AppSettingsController::class, 'edit'])->name('app-settings.edit');
    Route::post('app-settings', [AppSettingsController::class, 'update'])->name('app-settings.update');
    Route::delete('app-settings/logo', [AppSettingsController::class, 'destroyLogo'])
        ->name('app-settings.logo.destroy');
});

require __DIR__.'/settings.php';
