<?php

use Illuminate\Support\Facades\Route;
use TelegramBotEssentials\GatewayZibal\Http\Controllers\GatewayZibalController;

Route::prefix('invoice/{token}')->name('invoice.')->group(function () {
    Route::prefix('zibal')->name('zibal.')->controller(GatewayZibalController::class)->group(function () {
        Route::get('/pay', 'pay')->name('pay');
        Route::get('/callback', 'callback')->name('callback');
    });
});
