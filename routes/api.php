<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', 'AuthController@login');
Route::post('/register', 'AuthController@register');
Route::post('/payment/qris-webhook', 'PosController@qrisWebhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', 'AuthController@logout');

    // Kategori & Produk
    Route::get('/categories', 'ProductController@categories');
    Route::post('/categories', 'ProductController@storeCategory');
    Route::delete('/categories/{id}', 'ProductController@destroyCategory');
    Route::get('/categories/slug/{slug}', 'ProductController@productsByCategorySlug');
    Route::apiResource('/products', 'ProductController');
    Route::post('/products/{id}/opname', 'ProductController@stockOpname');

    // Bahan Baku
    Route::apiResource('/raw-materials', 'RawMaterialController');
    Route::post('/raw-materials/{id}/opname', 'RawMaterialController@stockOpname');

    // Kasir & Transaksi POS
    Route::post('/payment-pos', 'PosController@paymentPos');
    Route::post('/payment-qris/generate', 'PosController@generateQris');
    Route::get('/orders', 'PosController@listOrders');
    Route::post('/orders/report-order', 'PosController@reportOrder');
    Route::post('/orders/{id}/pay', 'PosController@markAsPaid');
    Route::post('/orders/{id}/cancel', 'PosController@cancelOrder');

    // Analitik
    Route::get('/analytics/dashboard', 'AnalyticsController@dashboard');

    // Cashflow
    Route::get('/cashflows', 'CashflowController@index');
    Route::post('/cashflows', 'CashflowController@store');
    Route::delete('/cashflows/{id}', 'CashflowController@destroy');

    // Promo & Pengaturan
    Route::apiResource('/promotions', 'CrudExtraController');
    Route::get('/settings', 'CrudExtraController@getSettings');
    Route::post('/settings', 'CrudExtraController@updateSettings');
});