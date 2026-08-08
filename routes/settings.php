<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    // Throttled: the `current_password` rule makes this a credential-guessing
    // oracle for anyone who gets hold of a session. The third argument is the
    // bucket prefix — a bare `throttle:10,1` would share one counter with every
    // other rate-limited route (see the gotcha in CLAUDE.md → Security hardening).
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:10,1,password-update')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
