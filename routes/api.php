<?php

use App\Http\Controllers\Api\WebsiteController;
use Illuminate\Support\Facades\Route;

// API d'échange avec le site web Mobilier Addict — en-tête obligatoire : X-API-KEY
Route::prefix('v1')->middleware(['website.key', 'throttle:website'])->group(function () {
    Route::get('/products', [WebsiteController::class, 'products']);
    Route::get('/stocks', [WebsiteController::class, 'stocks']);
    Route::post('/orders', [WebsiteController::class, 'storeOrder']);
    Route::get('/orders/{reference}', [WebsiteController::class, 'showOrder'])->where('reference', '[A-Za-z0-9._\-]+');
});
