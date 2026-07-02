<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.json', [SwaggerController::class, 'json'])->name('swagger.openapi');

Route::get('/internal/orders/{orderId}/payment', [PaymentController::class, 'paymentByOrder'])->whereNumber('orderId');
Route::get('/internal/orders/{orderId}/invoice', [PaymentController::class, 'invoiceByOrder'])->whereNumber('orderId');

Route::post('/payments/vnpay/create', [PaymentController::class, 'createVnpay']);
Route::get('/payments/vnpay/return', [PaymentController::class, 'vnpayReturn']);
Route::get('/payments/vnpay/ipn', [PaymentController::class, 'vnpayIpn']);

Route::get('/payments', [PaymentController::class, 'index']);
Route::post('/payments', [PaymentController::class, 'store']);

Route::get('/{resource}', [ResourceController::class, 'index']);
Route::post('/{resource}', [ResourceController::class, 'store']);
Route::get('/{resource}/{id}', [ResourceController::class, 'show'])->whereNumber('id');
Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::patch('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->whereNumber('id');
