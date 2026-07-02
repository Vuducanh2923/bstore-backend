<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.json', [SwaggerController::class, 'json'])->name('swagger.openapi');

Route::get('/carts/{id}', [CartController::class, 'show'])->whereNumber('id');
Route::post('/carts', [CartController::class, 'store']);

Route::middleware('customer.token')->prefix('customer')->group(function () {
    Route::get('/orders', [OrderController::class, 'customerOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'customerOrderDetail'])->whereNumber('id');
});

Route::middleware('admin.token')->prefix('admin')->group(function () {
    Route::get('/orders', [OrderController::class, 'adminOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'adminOrderDetail'])->whereNumber('id');
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateAdminOrderStatus'])->whereNumber('id');
});

Route::get('/internal/customers/{userId}/orders', [OrderController::class, 'internalCustomerOrders'])->whereNumber('userId');
Route::patch('/internal/orders/{orderId}/payment-status', [OrderController::class, 'internalUpdatePaymentStatus'])->whereNumber('orderId');
Route::post('/internal/orders/{orderId}/cart/clear', [CartController::class, 'clearForPaidOrder'])->whereNumber('orderId');

Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);

Route::get('/{resource}', [ResourceController::class, 'index']);
Route::post('/{resource}', [ResourceController::class, 'store']);
Route::get('/{resource}/{id}', [ResourceController::class, 'show'])->whereNumber('id');
Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::patch('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->whereNumber('id');
