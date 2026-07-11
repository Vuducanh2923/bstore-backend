<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.json', [SwaggerController::class, 'json'])->name('swagger.openapi');

Route::get('/carts/{id}', [CartController::class, 'show'])->whereNumber('id');
Route::post('/carts', [CartController::class, 'store']);

Route::middleware('customer.token')->prefix('customer')->group(function () {
    Route::get('/orders', [OrderController::class, 'customerOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'customerOrderDetail'])->whereNumber('id');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'requestCancel'])->whereNumber('id');
});

Route::middleware('admin.token')->prefix('admin')->group(function () {
    Route::get('/orders', [OrderController::class, 'adminOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'adminOrderDetail'])->whereNumber('id');
    Route::put('/orders/{id}/assign', [OrderController::class, 'assignToStaff'])->whereNumber('id');
    Route::patch('/orders/{id}/assign', [OrderController::class, 'assignToStaff'])->whereNumber('id');
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateAdminOrderStatus'])->whereNumber('id');
    Route::put('/orders/{id}/cancel/approve', [OrderController::class, 'approveCancel'])->whereNumber('id');
    Route::put('/orders/{id}/cancel/reject', [OrderController::class, 'rejectCancel'])->whereNumber('id');
});

Route::middleware('user.token')->group(function () {
    Route::get('/refunds', [RefundController::class, 'index']);
    Route::get('/refunds/{id}', [RefundController::class, 'show'])->whereNumber('id');
    Route::post('/refunds', [RefundController::class, 'store']);
    Route::put('/refunds/{id}/approve', [RefundController::class, 'approve'])->whereNumber('id');
    Route::put('/refunds/{id}/reject', [RefundController::class, 'reject'])->whereNumber('id');
    Route::put('/refunds/{id}/refunding', [RefundController::class, 'refunding'])->whereNumber('id');
    Route::put('/refunds/{id}/completed', [RefundController::class, 'completed'])->whereNumber('id');

    Route::get('/complaints', [ComplaintController::class, 'index']);
    Route::get('/complaints/{id}', [ComplaintController::class, 'show'])->whereNumber('id');
    Route::post('/complaints', [ComplaintController::class, 'store']);
    Route::put('/complaints/{id}/process', [ComplaintController::class, 'process'])->whereNumber('id');
    Route::put('/complaints/{id}/resolve', [ComplaintController::class, 'resolve'])->whereNumber('id');
    Route::put('/complaints/{id}/reject', [ComplaintController::class, 'reject'])->whereNumber('id');
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
