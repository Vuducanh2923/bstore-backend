<?php

use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.json', [SwaggerController::class, 'json'])->name('swagger.openapi');

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/verify-register-otp', [AuthController::class, 'verifyRegisterOtp']);
Route::post('/auth/resend-register-otp', [AuthController::class, 'resendRegisterOtp']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/verify-forgot-password-otp', [AuthController::class, 'verifyForgotPasswordOtp']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/debug-send-mail', [AuthController::class, 'debugSendMail']);

Route::get('/users', [UserController::class, 'index']);
Route::put('/users/{id}', [UserController::class, 'update'])->whereNumber('id');
Route::patch('/users/{id}', [UserController::class, 'update'])->whereNumber('id');

Route::middleware('customer')->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::put('/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/addresses', [ProfileController::class, 'addresses']);
    Route::post('/addresses', [ProfileController::class, 'storeAddress']);
    Route::put('/addresses/{id}', [ProfileController::class, 'updateAddress'])->whereNumber('id');
    Route::delete('/addresses/{id}', [ProfileController::class, 'destroyAddress'])->whereNumber('id');
    Route::patch('/addresses/{id}/default', [ProfileController::class, 'setDefaultAddress'])->whereNumber('id');
});

Route::middleware('admin.or.staff')->prefix('admin')->group(function () {
    Route::get('/customers', [UserManagementController::class, 'customers']);
    Route::get('/customers/{id}', [UserManagementController::class, 'showCustomer'])->whereNumber('id');
});

Route::middleware('admin')->prefix('admin')->group(function () {
    Route::patch('/customers/{id}/status', [UserManagementController::class, 'updateCustomerStatus'])->whereNumber('id');
    Route::delete('/customers/{id}', [UserManagementController::class, 'destroyCustomer'])->whereNumber('id');

    Route::get('/staff', [UserManagementController::class, 'staff']);
    Route::post('/staff', [UserManagementController::class, 'storeStaff']);
    Route::put('/staff/{id}', [UserManagementController::class, 'updateStaff'])->whereNumber('id');
    Route::patch('/staff/{id}/status', [UserManagementController::class, 'updateStaffStatus'])->whereNumber('id');
    Route::delete('/staff/{id}', [UserManagementController::class, 'destroyStaff'])->whereNumber('id');

    Route::patch('/users/{id}/role', [UserManagementController::class, 'updateRole'])->whereNumber('id');
});

Route::get('/{resource}', [ResourceController::class, 'index']);
Route::post('/{resource}', [ResourceController::class, 'store']);
Route::get('/{resource}/{id}', [ResourceController::class, 'show'])->whereNumber('id');
Route::put('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::patch('/{resource}/{id}', [ResourceController::class, 'update'])->whereNumber('id');
Route::delete('/{resource}/{id}', [ResourceController::class, 'destroy'])->whereNumber('id');
