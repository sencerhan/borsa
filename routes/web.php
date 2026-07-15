<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Dil tercihini oturumda saklar (tarayıcı dilini geçersiz kılar)
Route::post('locale', function (Request $request) {
    $locale = $request->string('locale')->value();
    if (in_array($locale, ['tr', 'en'], true)) {
        $request->session()->put('locale', $locale);
    }

    return back();
})->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/exchange.php';
require __DIR__.'/admin.php';
