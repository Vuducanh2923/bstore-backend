<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ResourceController;
use Illuminate\Support\Facades\Route;

Route::get('/payments', [PaymentController::class, 'index']);
Route::post('/payments', [PaymentController::class, 'store']);

Route::get('/{resource}', [ResourceController::class, 'index']);
Route::post('/{resource}', [ResourceController::class, 'store']);
Route::get('/{resource}/{id}', [ResourceController::class, 'show'])->whereNumber('id');
Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::patch('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->whereNumber('id');
