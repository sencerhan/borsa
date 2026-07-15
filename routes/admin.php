<?php

use App\Http\Controllers\Admin\MarketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('markets', [MarketController::class, 'edit'])->name('markets.edit');
    Route::patch('markets/{market}', [MarketController::class, 'update'])->name('markets.update');
});
