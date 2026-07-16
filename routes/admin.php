<?php

use App\Http\Controllers\Admin\MarketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('markets', [MarketController::class, 'edit'])->name('markets.edit');
    Route::patch('markets/{market}', [MarketController::class, 'update'])->name('markets.update');
    
    // Otonom Bot ve Simülatör Manuel Aksiyonları
    Route::post('markets/{market}/trigger-bot', [MarketController::class, 'triggerBot'])->name('markets.trigger_bot');
    Route::post('simulator/mock-deposit', [MarketController::class, 'triggerMockDeposit'])->name('simulator.mock_deposit');
    Route::post('simulator/mock-withdrawal', [MarketController::class, 'triggerMockWithdrawal'])->name('simulator.mock_withdrawal');
    Route::post('simulator/process-queue', [MarketController::class, 'processQueue'])->name('simulator.process_queue');
});
