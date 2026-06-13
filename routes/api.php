<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/users', [UserController::class, 'index']);
Route::put('/users/{id}', [UserController::class, 'update'])->whereNumber('id');
Route::patch('/users/{id}', [UserController::class, 'update'])->whereNumber('id');

Route::post('/uploads/images', [UploadController::class, 'image']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

Route::post('/carts', [CartController::class, 'store']);

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders', [OrderController::class, 'index']);

Route::post('/payments', [PaymentController::class, 'store']);
Route::get('/payments', [PaymentController::class, 'index']);

Route::get('/{resource}', [ResourceController::class, 'index']);
Route::post('/{resource}', [ResourceController::class, 'store']);
Route::get('/{resource}/{id}', [ResourceController::class, 'show'])->whereNumber('id');
Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::patch('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->whereNumber('id');
