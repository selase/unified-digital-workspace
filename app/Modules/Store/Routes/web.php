<?php

declare(strict_types=1);

use App\Modules\Store\Http\Controllers\Web\StoreWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreWebController::class, 'index'])->name('index');
Route::get('/platform', [StoreWebController::class, 'platformPackages'])->name('platform');
Route::post('/platform/purchase', [StoreWebController::class, 'purchasePlatformCredits'])->name('platform.purchase');
Route::get('/products', [StoreWebController::class, 'products'])->name('products');
Route::post('/products', [StoreWebController::class, 'storeProduct'])->name('products.store');
Route::get('/orders', [StoreWebController::class, 'orders'])->name('orders');
Route::post('/checkout', [StoreWebController::class, 'checkout'])->name('checkout');
