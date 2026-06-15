<?php

use App\Http\Controllers\SwaggerController;
use App\Http\Controllers\Api\GatewayController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.json', [SwaggerController::class, 'json'])->name('swagger.openapi');
Route::get('/gateway/health', [GatewayController::class, 'health']);

Route::any('/{path}', [GatewayController::class, 'forward'])
    ->where('path', '.*');
