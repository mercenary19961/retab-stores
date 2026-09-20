<?php

use App\Http\Controllers\Auth\AdminSessionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:10,1,register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // LoginRequest already limits to 5 attempts per email+IP; this adds a global
    // per-IP cap so credential-stuffing across many emails from one IP is bounded.
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1,login');

    // WhatsApp OTP sign-in / sign-up (the decided primary method for customers).
    Route::get('login/whatsapp', [OtpAuthController::class, 'create'])->name('login.whatsapp');
    Route::post('login/whatsapp/send', [OtpAuthController::class, 'send'])
        ->middleware('throttle:6,1,otp-send')->name('login.whatsapp.send');
    Route::post('login/whatsapp/verify', [OtpAuthController::class, 'verify'])
        ->middleware('throttle:6,1,otp-verify')->name('login.whatsapp.verify');

    // Staff front door. Separate from the customers' /login: no social sign-in
    // and no sign-up link, because staff accounts exist only at /admin/users.
    // Shares LoginRequest, so the throttle and lockout are the same.
    Route::get('admin/login', [AdminSessionController::class, 'create'])->name('admin.login');
    Route::post('admin/login', [AdminSessionController::class, 'store'])->name('admin.login.store');

    // Sign in with Google. Both 404 unless a Google OAuth client is configured.
    // Own throttle bucket — a bare `throttle:N,1` rejoins the shared per-visitor
    // counter that once 429'd shoppers at checkout (2026-08-06).
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:10,1,google-auth')->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:10,1,google-auth')->name('auth.google.callback');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1,password-email')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1,password-reset')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1,verify-email'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1,verify-send')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
