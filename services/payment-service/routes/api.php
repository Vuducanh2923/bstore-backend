<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.json', [SwaggerController::class, 'json'])->name('swagger.openapi');

Route::get('/payments/vnpay/return', [PaymentController::class, 'vnpayReturn']);
Route::get('/payments/vnpay/ipn', [PaymentController::class, 'vnpayIpn']);

Route::middleware('customer.token')->group(function () {
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/payments/vnpay/create', [PaymentController::class, 'createVnpay']);
});

Route::middleware('admin.token')->get('/payments', [PaymentController::class, 'index']);

Route::middleware('internal.service')->prefix('internal')->group(function () {
    Route::get('/orders/{orderId}/payment', [PaymentController::class, 'paymentByOrder'])->whereNumber('orderId');
    Route::get('/orders/{orderId}/invoice', [PaymentController::class, 'invoiceByOrder'])->whereNumber('orderId');
    Route::post('/payments/{orderId}/refunds', [PaymentController::class, 'refund'])->whereNumber('orderId');
});
