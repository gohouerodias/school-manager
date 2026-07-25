<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'account.active', '2fa', 'password.changed'])->group(function () {
    Route::get('tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard');
});
