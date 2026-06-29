<?php

use App\Http\Controllers\TransactionPrintController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(Authenticate::class)->group(function (): void {
    Route::get('/admin/transactions/{transaction}/print', TransactionPrintController::class)
        ->name('transactions.print');
});
