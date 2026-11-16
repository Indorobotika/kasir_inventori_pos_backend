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
Route::get('/test-koneksi', function () {
    return response()->json([
        'status'  => 'success',
        'message' => 'Backend Laravel 6 berhasil terhubung dengan Frontend!'
    ], 200);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', 'AuthController@logout');

    // Kategori & Produk
    Route::get('/categories', 'ProductController@categories');
    Route::post('/categories', 'ProductController@storeCategory');
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