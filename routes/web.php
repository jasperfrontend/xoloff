<?php

use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Portal\QuotePortalController;
use App\Http\Controllers\PremadeTextController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\QuotePreviewController;
use App\Http\Controllers\QuoteSendController;
use App\Http\Controllers\QuoteVersionController;
use App\Http\Controllers\TaxClassController;
use Illuminate\Support\Facades\Route;

// Xoloff has no public front page - it is an internal tool for two people.
// The root sends you into the app, and the auth middleware bounces guests
// to the login screen from there.
Route::redirect('/', '/dashboard')->name('home');

// The magic link, and the only page in xoloff a customer ever reaches (SPEC
// §7). Public by design: the token in the address is the whole credential,
// which is why it is sized like a password and hidden on the model. Dutch in
// the address because it is read by Dutch customers, not by either of the two
// people who use the rest of the app.
Route::get('offerte/{quote:magic_link_token}', QuotePortalController::class)
    ->name('portal.quote');

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

    // "Send quote" (SPEC §7): issues the magic link, sets the validity
    // window and moves the quote to sent.
    Route::post('quotes/{quote}/send', QuoteSendController::class)
        ->name('quotes.send');

    // "Download PDF" (SPEC §6). Two routes rather than one, because a quote
    // already sent has to be reprintable exactly as it went out.
    Route::get('quotes/{quote}/pdf', [QuotePdfController::class, 'current'])
        ->name('quotes.pdf');
    Route::get('quotes/{quote}/versions/{version}/pdf', [QuotePdfController::class, 'version'])
        ->name('quotes.versions.pdf');

    // The history of a quote: browse it, read a superseded version, remove one
    // (SPEC §6). There is no edit route, because rewriting a past version is
    // what versioning exists to prevent.
    Route::get('quotes/{quote}/versions', [QuoteVersionController::class, 'index'])
        ->name('quotes.versions.index');
    Route::get('quotes/{quote}/versions/{version}', [QuoteVersionController::class, 'show'])
        ->name('quotes.versions.show');
    Route::delete('quotes/{quote}/versions/{version}', [QuoteVersionController::class, 'destroy'])
        ->name('quotes.versions.destroy');

    // The intro and footer carried by every quote (SPEC §3). Exactly two rows,
    // fixed by key, so this is an edit screen rather than a resource.
    Route::get('premade-texts', [PremadeTextController::class, 'edit'])->name('premade-texts.edit');
    Route::put('premade-texts', [PremadeTextController::class, 'update'])->name('premade-texts.update');

    // Everything that has happened, filterable by quote, by date range and by
    // who caused it (SPEC §3). Read-only: an audit log nobody can edit is the
    // only kind worth keeping.
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    // Application-wide configuration, as opposed to the per-user screens under
    // /settings. One row, so this edits rather than creating (SPEC §3).
    // The logo saves separately from the typed settings: a file input cannot
    // be redisplayed with what was submitted, so one shared form would drop
    // the chosen file whenever anything else on the screen failed validation.
    Route::get('app-settings', [AppSettingsController::class, 'edit'])->name('app-settings.edit');
    Route::put('app-settings', [AppSettingsController::class, 'update'])->name('app-settings.update');
    Route::post('app-settings/logo', [AppSettingsController::class, 'storeLogo'])
        ->name('app-settings.logo.store');
    Route::delete('app-settings/logo', [AppSettingsController::class, 'destroyLogo'])
        ->name('app-settings.logo.destroy');
});

require __DIR__.'/settings.php';
