<?php

use App\Http\Controllers\Api\WebsiteController;
use Illuminate\Support\Facades\Route;

/*
| API d'échange avec le site web Mobilier Addict.
| Authentification : en-tête « Authorization: Bearer <jeton> » (jeton créé dans Administration → API site web).
*/
Route::prefix('v1')->middleware(['api.client', 'throttle:website-api'])->name('api.')->group(function () {
    Route::get('/ping', [WebsiteController::class, 'ping'])->name('ping');
    Route::get('/stores', [WebsiteController::class, 'stores'])->name('stores');
    Route::get('/products', [WebsiteController::class, 'products'])->name('products');
    Route::get('/products/{reference}', [WebsiteController::class, 'product'])->name('product');
    Route::get('/stock', [WebsiteController::class, 'stock'])->name('stock');
    Route::post('/orders', [WebsiteController::class, 'storeOrder'])->name('orders.store');
    Route::get('/orders/{externalReference}', [WebsiteController::class, 'showOrder'])->name('orders.show');
    Route::post('/orders/{externalReference}/cancel', [WebsiteController::class, 'cancelOrder'])->name('orders.cancel');
});
