<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/comptes.php';
require __DIR__.'/eleves.php';
require __DIR__.'/tuteurs.php';
require __DIR__.'/academique.php';

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'account.active', '2fa', 'password.changed'])->group(function () {
    Route::get('tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard');
    Route::patch('mon-profil', [ProfileController::class, 'update'])->name('profil.update');
});
