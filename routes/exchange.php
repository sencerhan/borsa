<?php

use App\Http\Controllers\Exchange\ExchangeController;
use App\Http\Controllers\Exchange\OrderController;
use App\Http\Controllers\Exchange\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // İşlem (trading) ekranı
    Route::get('exchange', [ExchangeController::class, 'index'])->name('exchange.index');

    // Emirler
    Route::post('exchange/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::delete('exchange/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

    // Cüzdan (yatırma/çekme)
    // NOT: Yatırma artık kullanıcı tarafından tutar girilerek yapılmaz. Kullanıcı,
    // cüzdan sayfasındaki deposit adresine zincir üzerinden gerçek transfer yapar;
    // bakiye ancak DepositVerificationService transferi doğrulayınca eklenir.
    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('wallet/withdraw', [WalletController::class, 'withdraw'])->name('wallet.withdraw');
});
