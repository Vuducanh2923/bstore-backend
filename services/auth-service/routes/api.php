<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/users', [UserController::class, 'index']);
Route::put('/users/{id}', [UserController::class, 'update'])->whereNumber('id');
Route::patch('/users/{id}', [UserController::class, 'update'])->whereNumber('id');

Route::get('/{resource}', [ResourceController::class, 'index']);
Route::post('/{resource}', [ResourceController::class, 'store']);
Route::get('/{resource}/{id}', [ResourceController::class, 'show'])->whereNumber('id');
Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::patch('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->whereNumber('id');
