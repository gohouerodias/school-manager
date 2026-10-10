<?php

use App\Http\Controllers\Auth\AccountActivationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForcePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('connexion', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:connexion');

    Route::get('mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])->middleware('throttle:sensible')->name('password.email');

    Route::get('reinitialiser-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reinitialiser-mot-de-passe', [NewPasswordController::class, 'store'])->middleware('throttle:sensible')->name('password.reset.update');

    // Écran "Activer votre compte" : mécanisme de jeton identique à la
    // réinitialisation ci-dessus (voir AccountActivationController), mais
    // page + messages distincts pour un compte fraîchement créé par un
    // administrateur — voir App\Notifications\ResetPasswordNotification et
    // User::estEnAttenteActivation().
    Route::get('activer-mon-compte/{token}', [AccountActivationController::class, 'create'])->name('compte.activer');
    Route::post('activer-mon-compte', [AccountActivationController::class, 'store'])->middleware('throttle:sensible')->name('compte.activer.update');
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('verification-2fa', [TwoFactorChallengeController::class, 'create'])->name('2fa.challenge');
    Route::post('verification-2fa', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:sensible')->name('2fa.verify');

    Route::middleware('2fa')->group(function () {
        Route::get('nouveau-mot-de-passe', [ForcePasswordController::class, 'create'])->name('password.force');
        Route::put('nouveau-mot-de-passe', [ForcePasswordController::class, 'update'])->name('password.update');
    });

    Route::post('deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
