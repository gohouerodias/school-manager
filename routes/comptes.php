<?php

use App\Http\Controllers\Comptes\UserAccountController;
use App\Http\Controllers\Comptes\UserAccountExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'account.active', '2fa', 'password.changed', 'profile:administrateur'])
    ->prefix('comptes')
    ->name('comptes.')
    ->group(function () {
        Route::get('/', [UserAccountController::class, 'index'])->name('index');
        Route::post('/', [UserAccountController::class, 'store'])->name('store');
        Route::patch('{user}', [UserAccountController::class, 'update'])->name('update');
        Route::patch('{user}/archiver', [UserAccountController::class, 'archiver'])->name('archiver');
        Route::patch('{user}/reactiver', [UserAccountController::class, 'reactiver'])->name('reactiver');

        Route::get('export/excel', [UserAccountExportController::class, 'excel'])->name('export.excel');
        Route::get('export/pdf', [UserAccountExportController::class, 'pdf'])->name('export.pdf');
    });
