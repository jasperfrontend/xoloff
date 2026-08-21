<?php

use App\Http\Controllers\AppSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth'])->group(function () {
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::middleware(['auth'])->group(function () {
    // Configuration shared by everyone, as opposed to the per-user screens
    // above. One row, so this edits rather than creating (SPEC §3).
    //
    // It sits under /settings because that is what it is. It had a main menu
    // item of its own and a page with no settings navigation on it, which made
    // two kinds of settings look like two unrelated parts of the application.
    Route::get('settings/app', [AppSettingsController::class, 'edit'])->name('app-settings.edit');
    Route::put('settings/app', [AppSettingsController::class, 'update'])->name('app-settings.update');

    // The logo saves separately from the typed settings, because fetching it
    // can fail for reasons that have nothing to do with the other fields and a
    // KvK number should not be held hostage to a web server being briefly
    // down. Both addresses save together; clearing one removes that logo, so
    // there is no separate delete to keep in step with this.
    Route::put('settings/app/logo', [AppSettingsController::class, 'storeLogo'])
        ->name('app-settings.logo.store');
});
